<?php

namespace Tests\Feature;

use App\Exceptions\InvalidLotStateException;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\User;
use App\Models\WarehouseBin;
use App\Services\DeliveryOrderService;
use App\Services\FefoAllocationService;
use App\Services\InventoryMovementService;
use App\Services\LotQualityService;
use Database\Seeders\ItemCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WarehouseTopologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WmsCoreDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(WarehouseTopologySeeder::class);
        $this->seed(ItemCatalogSeeder::class);
    }

    public function test_inbound_receipt_and_putaway_creates_balanced_ledger(): void
    {
        $movementService = app(InventoryMovementService::class);

        $item = Item::where('sku', 'RAW-PP-INJ-503')->firstOrFail();
        $bin1 = WarehouseBin::where('bin_code', 'RM-A-01-11')->firstOrFail();
        $bin2 = WarehouseBin::where('bin_code', 'RM-A-01-12')->firstOrFail();

        $lot = MaterialLot::create([
            'lot_number' => 'LOT-PP-20261030-01',
            'item_id' => $item->id,
            'vendor_code' => 'VEND-HYOSUNG',
            'vendor_lot_number' => 'HY-8899',
            'vendor_heat_number' => 'HEAT-4411',
            'expiry_date' => now()->addYear(),
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);

        // 1. Inbound receipt 500 KG to Bin 1
        $receipt = $movementService->recordReceipt(
            item: $item,
            lot: $lot,
            destinationBin: $bin1,
            quantity: 500.0,
            referenceId: 'PO-2026-9901'
        );

        $this->assertEquals(InventoryMovement::TYPE_RECEIPT, $receipt->movement_type);
        $this->assertDatabaseHas('inventory_balances', [
            'bin_id' => $bin1->id,
            'material_lot_id' => $lot->id,
            'quantity_on_hand' => 500.0,
        ]);

        // 2. Internal Putaway 200 KG from Bin 1 to Bin 2
        $putaway = $movementService->recordPutaway(
            lot: $lot,
            sourceBin: $bin1,
            destinationBin: $bin2,
            quantity: 200.0
        );

        $this->assertEquals(InventoryMovement::TYPE_PUTAWAY, $putaway->movement_type);

        $bin1Balance = InventoryBalance::where('bin_id', $bin1->id)->where('material_lot_id', $lot->id)->first();
        $bin2Balance = InventoryBalance::where('bin_id', $bin2->id)->where('material_lot_id', $lot->id)->first();

        $this->assertEquals(300.0, (float) $bin1Balance->quantity_on_hand);
        $this->assertEquals(200.0, (float) $bin2Balance->quantity_on_hand);

        // 3. Invariant check: total physical stock matches total received
        $totalOnHand = InventoryBalance::where('material_lot_id', $lot->id)->sum('quantity_on_hand');
        $this->assertEquals(500.0, (float) $totalOnHand);
    }

    public function test_fefo_allocation_prioritizes_earliest_expiry_and_skips_qa_hold(): void
    {
        $movementService = app(InventoryMovementService::class);
        $fefoService = app(FefoAllocationService::class);

        $item = Item::where('sku', 'RAW-PP-INJ-503')->firstOrFail();
        $bin = WarehouseBin::where('bin_code', 'RM-A-01-11')->firstOrFail();

        // Lot A: expires in 6 months (Status: AVAILABLE)
        $lotA = MaterialLot::create([
            'lot_number' => 'LOT-EXP-EARLY',
            'item_id' => $item->id,
            'expiry_date' => now()->addMonths(6),
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);
        $movementService->recordReceipt($item, $lotA, $bin, 100.0);

        // Lot B: expires in 1 month (Status: QA_HOLD - must be skipped by FEFO)
        $lotB = MaterialLot::create([
            'lot_number' => 'LOT-EXP-VERY-EARLY-HOLD',
            'item_id' => $item->id,
            'expiry_date' => now()->addMonth(),
            'status' => MaterialLot::STATUS_QA_HOLD,
        ]);
        $movementService->recordReceipt($item, $lotB, $bin, 100.0);

        // Lot C: expires in 12 months (Status: AVAILABLE)
        $lotC = MaterialLot::create([
            'lot_number' => 'LOT-EXP-LATE',
            'item_id' => $item->id,
            'expiry_date' => now()->addMonths(12),
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);
        $movementService->recordReceipt($item, $lotC, $bin, 100.0);

        // Allocate 150 KG
        $allocations = $fefoService->allocate($item, 150.0);

        // Should take 100 KG from Lot A (earliest available), 0 from Lot B (on hold), and 50 KG from Lot C
        $this->assertCount(2, $allocations);
        $this->assertEquals('LOT-EXP-EARLY', $allocations[0]['lot_number']);
        $this->assertEquals(100.0, $allocations[0]['quantity']);

        $this->assertEquals('LOT-EXP-LATE', $allocations[1]['lot_number']);
        $this->assertEquals(50.0, $allocations[1]['quantity']);

        // Check reservations
        $balanceA = InventoryBalance::where('material_lot_id', $lotA->id)->first();
        $this->assertEquals(100.0, (float) $balanceA->quantity_reserved);

        $balanceC = InventoryBalance::where('material_lot_id', $lotC->id)->first();
        $this->assertEquals(50.0, (float) $balanceC->quantity_reserved);
    }

    public function test_lot_quality_service_transition_guards(): void
    {
        $qualityService = app(LotQualityService::class);
        $item = Item::where('sku', 'RAW-ABS-HI-121')->firstOrFail();
        $inspector = User::where('email', 'qa@wms.local')->firstOrFail();

        $lot = MaterialLot::create([
            'lot_number' => 'LOT-QA-TEST-01',
            'item_id' => $item->id,
            'expiry_date' => now()->addYear(),
            'status' => MaterialLot::STATUS_QA_HOLD,
        ]);

        // Release to available
        $released = $qualityService->releaseToAvailable(
            lot: $lot,
            inspector: $inspector,
            coaMetrics: ['moisture_pct' => 0.02, 'melt_flow_index' => 12.5]
        );

        $this->assertEquals(MaterialLot::STATUS_AVAILABLE, $released->status);
        $this->assertEquals($inspector->id, $released->qc_inspected_by);
        $this->assertEquals(0.02, $released->coa_metrics['moisture_pct']);

        // Cannot release an already available lot (InvalidLotStateException)
        $this->expectException(InvalidLotStateException::class);
        $qualityService->releaseToAvailable($released, $inspector);
    }

    public function test_delivery_order_dispatch_and_pdf_generation(): void
    {
        $movementService = app(InventoryMovementService::class);
        $deliveryOrderService = app(DeliveryOrderService::class);

        $item = Item::where('sku', 'PART-BZL-FR-LH')->firstOrFail();
        $fgBin = WarehouseBin::where('bin_code', 'FG-A-01-01')->firstOrFail();
        $user = User::where('email', 'admin@wms.local')->firstOrFail();

        $fgLot = MaterialLot::create([
            'lot_number' => 'LOT-FG-BZL-001',
            'item_id' => $item->id,
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);

        // Stock FG bin with 200 PCS
        $movementService->recordFinishedGoodsIntake($item, $fgLot, $fgBin, 200.0, 'CARTON-TEST-01');

        $order = DeliveryOrder::create([
            'do_number' => 'DO-202610-001',
            'customer_name' => 'Apex Automotive Assemblies Inc',
            'shipping_address' => 'Plant 2 Inbound Dock, Industrial Corridor, West Java',
            'vehicle_plate_number' => 'B 9876 KDA',
            'driver_name' => 'John Doe',
            'status' => DeliveryOrder::STATUS_DRAFT,
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'item_id' => $item->id,
            'material_lot_id' => $fgLot->id,
            'carton_barcode' => 'CARTON-TEST-01',
            'quantity_ordered' => 200.0,
            'quantity_loaded' => 0,
            'source_bin_id' => $fgBin->id,
        ]);

        // 1. Scan carton barcode
        $scanResult = $deliveryOrderService->scanCarton($order, 'CARTON-TEST-01');
        $this->assertTrue($scanResult['success']);
        $this->assertEquals(DeliveryOrder::STATUS_LOADED, $order->fresh()->status);

        // 2. Dispatch
        $dispatched = $deliveryOrderService->dispatch($order->fresh(), $user);
        $this->assertEquals(DeliveryOrder::STATUS_DISPATCHED, $dispatched->status);

        // Verify balance decremented to 0
        $balance = InventoryBalance::where('bin_id', $fgBin->id)->where('material_lot_id', $fgLot->id)->first();
        $this->assertEquals(0.0, (float) $balance->quantity_on_hand);

        // 3. Generate PDF
        $pdfOutput = $deliveryOrderService->generatePdf($order);
        $this->assertNotEmpty($pdfOutput);
        $this->assertStringStartsWith('%PDF-', $pdfOutput);
    }
}
