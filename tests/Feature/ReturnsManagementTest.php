<?php

namespace Tests\Feature;

use App\Models\CustomerRma;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\SupplierRtv;
use App\Models\User;
use App\Models\WarehouseBin;
use App\Services\CustomerRmaService;
use App\Services\SupplierRtvService;
use Database\Seeders\ItemCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WarehouseTopologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReturnsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(WarehouseTopologySeeder::class);
        $this->seed(ItemCatalogSeeder::class);
    }

    public function test_customer_rma_creation_and_carton_dock_receiving_into_quarantine(): void
    {
        $operator = User::factory()->create();
        Sanctum::actingAs($operator);

        $item = Item::where('category', Item::CATEGORY_FINISHED_GOOD)->first() ?? Item::firstOrFail();
        $quarantineBin = WarehouseBin::where('bin_code', 'like', '%QUARANTINE%')->first() ?? WarehouseBin::firstOrFail();

        $lot = MaterialLot::create([
            'item_id' => $item->id,
            'lot_number' => 'LOT-FG-RET-01',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 50.0,
            'remaining_quantity' => 50.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'manufactured_date' => now()->subDays(5),
            'expiry_date' => now()->addYear(),
        ]);

        /** @var CustomerRmaService $rmaService */
        $rmaService = app(CustomerRmaService::class);
        $rma = $rmaService->createRma([
            'customer_name' => 'Astra Component Assembly',
            'defect_reason' => 'WARPAGE',
        ], [
            [
                'item_id' => $item->id,
                'material_lot_id' => $lot->id,
                'carton_barcode' => 'CART-RMA-001',
                'quantity' => 10.0,
            ],
            [
                'item_id' => $item->id,
                'material_lot_id' => $lot->id,
                'carton_barcode' => 'CART-RMA-002',
                'quantity' => 15.0,
            ],
        ]);

        $this->assertEquals(CustomerRma::STATUS_PENDING_RECEIPT, $rma->status);
        $this->assertEquals(2, $rma->total_cartons);
        $this->assertEquals(0, $rma->received_cartons);

        // Receive first carton via Scanner API
        $response1 = $this->postJson('/api/v1/scanner/returns/receive-carton', [
            'rma_id' => $rma->id,
            'carton_barcode' => 'CART-RMA-001',
            'quarantine_bin_code' => $quarantineBin->bin_code,
            'quantity' => 10.0,
        ]);

        $response1->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('received_cartons', 1);

        $rma->refresh();
        $this->assertEquals(CustomerRma::STATUS_PENDING_RECEIPT, $rma->status);

        // Check quarantine bin balance
        $balance = InventoryBalance::where('bin_id', $quarantineBin->id)
            ->where('item_id', $item->id)
            ->where('material_lot_id', $lot->id)
            ->first();
        $this->assertNotNull($balance);
        $this->assertEquals(10.0, (float) $balance->quantity_on_hand);

        // Receive second carton
        $response2 = $this->postJson('/api/v1/scanner/returns/receive-carton', [
            'rma_id' => $rma->id,
            'carton_barcode' => 'CART-RMA-002',
            'quarantine_bin_code' => $quarantineBin->bin_code,
            'quantity' => 15.0,
        ]);

        $response2->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('received_cartons', 2);

        $rma->refresh();
        $this->assertEquals(CustomerRma::STATUS_RECEIVED, $rma->status);

        $balance->refresh();
        $this->assertEquals(25.0, (float) $balance->quantity_on_hand);

        // Verify movement ledger
        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => InventoryMovement::TYPE_CUSTOMER_RETURN,
            'destination_bin_id' => $quarantineBin->id,
            'reference_type' => 'CUSTOMER_RMA',
            'reference_id' => $rma->rma_number,
        ]);
    }

    public function test_customer_rma_qa_disposition_restock(): void
    {
        $inspector = User::factory()->create();
        $item = Item::where('category', Item::CATEGORY_FINISHED_GOOD)->first() ?? Item::firstOrFail();
        $quarantineBin = WarehouseBin::where('bin_code', 'like', '%QUARANTINE%')->first() ?? WarehouseBin::firstOrFail();
        $targetBin = WarehouseBin::where('id', '!=', $quarantineBin->id)->firstOrFail();

        $lot = MaterialLot::create([
            'item_id' => $item->id,
            'lot_number' => 'LOT-FG-RESTOCK-01',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 20.0,
            'remaining_quantity' => 20.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'manufactured_date' => now()->subDays(3),
            'expiry_date' => now()->addYear(),
        ]);

        /** @var CustomerRmaService $rmaService */
        $rmaService = app(CustomerRmaService::class);
        $rma = $rmaService->createRma([
            'customer_name' => 'Toyota Motor Mfg',
            'defect_reason' => 'TRANSIT_DAMAGE',
        ], [
            [
                'item_id' => $item->id,
                'material_lot_id' => $lot->id,
                'carton_barcode' => 'CART-TOYOTA-01',
                'quantity' => 20.0,
            ],
        ]);

        $rmaService->receiveCarton($rma, 'CART-TOYOTA-01', $quarantineBin, 20.0, $inspector);

        // Execute Restock Disposition
        $resolvedRma = $rmaService->processDisposition(
            rma: $rma,
            disposition: CustomerRma::DISPOSITION_RESTOCK,
            quarantineBin: $quarantineBin,
            targetBin: $targetBin,
            inspector: $inspector,
            notes: 'Packaging damaged during freight, parts intact and dimensionally verified'
        );

        $this->assertEquals(CustomerRma::STATUS_RESOLVED, $resolvedRma->status);
        $this->assertEquals(CustomerRma::DISPOSITION_RESTOCK, $resolvedRma->disposition);
        $this->assertNotNull($resolvedRma->credit_memo_number);

        // Quarantine bin balance should be 0
        $quarantineBal = InventoryBalance::where('bin_id', $quarantineBin->id)
            ->where('material_lot_id', $lot->id)
            ->first();
        $this->assertEquals(0.0, (float) ($quarantineBal?->quantity_on_hand ?? 0));

        // Target bin balance should have 20.0
        $targetBal = InventoryBalance::where('bin_id', $targetBin->id)
            ->where('material_lot_id', $lot->id)
            ->first();
        $this->assertNotNull($targetBal);
        $this->assertEquals(20.0, (float) $targetBal->quantity_on_hand);
    }

    public function test_customer_rma_qa_disposition_scrap_disposal(): void
    {
        $inspector = User::factory()->create();
        $item = Item::where('category', Item::CATEGORY_FINISHED_GOOD)->first() ?? Item::firstOrFail();
        $quarantineBin = WarehouseBin::where('bin_code', 'like', '%QUARANTINE%')->first() ?? WarehouseBin::firstOrFail();

        $lot = MaterialLot::create([
            'item_id' => $item->id,
            'lot_number' => 'LOT-FG-SCRAP-01',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 15.0,
            'remaining_quantity' => 15.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'manufactured_date' => now()->subDays(4),
            'expiry_date' => now()->addYear(),
        ]);

        /** @var CustomerRmaService $rmaService */
        $rmaService = app(CustomerRmaService::class);
        $rma = $rmaService->createRma([
            'customer_name' => 'Honda Prospect Motor',
            'defect_reason' => 'BURNT_MATERIAL',
        ], [
            [
                'item_id' => $item->id,
                'material_lot_id' => $lot->id,
                'carton_barcode' => 'CART-HONDA-01',
                'quantity' => 15.0,
            ],
        ]);

        $rmaService->receiveCarton($rma, 'CART-HONDA-01', $quarantineBin, 15.0, $inspector);

        // Execute Scrap Disposal
        $resolvedRma = $rmaService->processDisposition(
            rma: $rma,
            disposition: CustomerRma::DISPOSITION_SCRAP_DISPOSAL,
            quarantineBin: $quarantineBin,
            inspector: $inspector,
            notes: 'Severe polymer degradation, non-recyclable'
        );

        $this->assertEquals(CustomerRma::STATUS_RESOLVED, $resolvedRma->status);
        $this->assertEquals(CustomerRma::DISPOSITION_SCRAP_DISPOSAL, $resolvedRma->disposition);

        // Quarantine bin should be zero
        $quarantineBal = InventoryBalance::where('bin_id', $quarantineBin->id)
            ->where('material_lot_id', $lot->id)
            ->first();
        $this->assertEquals(0.0, (float) ($quarantineBal?->quantity_on_hand ?? 0));

        // Movement should be TYPE_SCRAP
        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => InventoryMovement::TYPE_SCRAP,
            'source_bin_id' => $quarantineBin->id,
            'reference_type' => 'SCRAP_DISPOSITION',
            'reference_id' => $rma->rma_number,
        ]);
    }

    public function test_supplier_rtv_lifecycle_draft_approve_and_dispatch(): void
    {
        $logisticsUser = User::factory()->create();
        $rawItem = Item::where('category', Item::CATEGORY_RAW_RESIN)->first() ?? Item::firstOrFail();
        $stagingBin = WarehouseBin::firstOrFail();

        $defectiveLot = MaterialLot::create([
            'item_id' => $rawItem->id,
            'lot_number' => 'LOT-DEFECTIVE-PP-01',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'vendor_code' => 'VND-LOTTE',
            'initial_quantity' => 500.0,
            'remaining_quantity' => 500.0,
            'status' => MaterialLot::STATUS_QUARANTINE,
            'manufactured_date' => now()->subDays(10),
            'expiry_date' => now()->addYear(),
        ]);

        InventoryBalance::create([
            'bin_id' => $stagingBin->id,
            'item_id' => $rawItem->id,
            'material_lot_id' => $defectiveLot->id,
            'quantity_on_hand' => 500.0,
            'quantity_reserved' => 0.0,
        ]);

        /** @var SupplierRtvService $rtvService */
        $rtvService = app(SupplierRtvService::class);

        // 1. Create RTV Draft
        $rtv = $rtvService->createRtv([
            'vendor_code' => 'VND-LOTTE',
            'vendor_name' => 'Lotte Chemical Titan',
            'po_number' => 'PO-2026-9901',
            'material_lot_id' => $defectiveLot->id,
            'warehouse_bin_id' => $stagingBin->id,
            'quantity_kg' => 500.0,
            'reason' => 'Failed Melt Flow Index (MFI)',
        ]);

        $this->assertEquals(SupplierRtv::STATUS_DRAFT, $rtv->status);
        $this->assertStringStartsWith('RTV-', $rtv->rtv_number);

        // 2. Approve RTV
        $approvedRtv = $rtvService->approveRtv($rtv);
        $this->assertEquals(SupplierRtv::STATUS_APPROVED, $approvedRtv->status);

        // 3. Dispatch Outbound with Debit Memo
        $dispatchResult = $rtvService->dispatchRtv($approvedRtv, 'DM-LOTTE-2026-001', $logisticsUser);

        $this->assertEquals('success', $dispatchResult['status']);
        $approvedRtv->refresh();
        $this->assertEquals(SupplierRtv::STATUS_DISPATCHED, $approvedRtv->status);
        $this->assertEquals('DM-LOTTE-2026-001', $approvedRtv->debit_memo_number);
        $this->assertNotNull($approvedRtv->dispatched_at);
        $this->assertEquals($logisticsUser->id, $approvedRtv->dispatched_by);

        // Stock in bin should be 0
        $balance = InventoryBalance::where('bin_id', $stagingBin->id)
            ->where('material_lot_id', $defectiveLot->id)
            ->first();
        $this->assertEquals(0.0, (float) ($balance?->quantity_on_hand ?? 0));

        // Movement ledger should record TYPE_RETURN_TO_VENDOR
        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => InventoryMovement::TYPE_RETURN_TO_VENDOR,
            'source_bin_id' => $stagingBin->id,
            'reference_type' => 'SUPPLIER_RTV',
            'reference_id' => $approvedRtv->rtv_number,
        ]);
    }

    public function test_scanner_pending_returns_api(): void
    {
        $operator = User::factory()->create();
        Sanctum::actingAs($operator);

        $item = Item::firstOrFail();
        /** @var CustomerRmaService $rmaService */
        $rmaService = app(CustomerRmaService::class);
        $rma = $rmaService->createRma([
            'customer_name' => 'Yamaha Musical Products',
            'defect_reason' => 'FLASH',
        ], [
            [
                'item_id' => $item->id,
                'carton_barcode' => 'CART-YAMAHA-01',
                'quantity' => 5.0,
            ],
        ]);

        $response = $this->getJson('/api/v1/scanner/returns/pending');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['rma_number' => $rma->rma_number]);
    }
}
