<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\WarehouseBin;
use App\Services\FifoAllocationService;
use App\Services\InventoryMovementService;
use Database\Seeders\ItemCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WarehouseTopologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcurrencyAndInvariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(WarehouseTopologySeeder::class);
        $this->seed(ItemCatalogSeeder::class);
    }

    public function test_zero_sum_mathematical_ledger_invariant(): void
    {
        $movementService = app(InventoryMovementService::class);
        $item = Item::where('sku', 'RAW-PP-INJ-503')->firstOrFail();
        $dockBin = WarehouseBin::where('bin_code', 'RM-A-01-11')->firstOrFail();
        $rackBin = WarehouseBin::where('bin_code', 'RM-A-01-12')->firstOrFail();
        $stgBin = WarehouseBin::where('bin_code', 'STG-IMM-01')->firstOrFail();

        $lot = MaterialLot::create([
            'lot_number' => 'LOT-INVARIANT-TEST',
            'item_id' => $item->id,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'received_at' => now()->subDays(5),
        ]);

        // Step 1: Inbound 1000 kg
        $movementService->recordReceipt($item, $lot, $dockBin, 1000.0, 'PO-INV-01');

        // Step 2: Putaway 400 kg from dock to rack
        $movementService->recordPutaway($lot, $dockBin, $rackBin, 400.0);

        // Step 3: Stage 250 kg from rack to machine staging buffer
        $movementService->recordStagingIssue($lot, $rackBin, $stgBin, 250.0, 'MES-JOB-110');

        // Step 4: Outbound shipment of 100 kg from dock
        $movementService->recordShipment($lot, $dockBin, 100.0, 'DO-INV-01');

        // MATHEMATICAL AUDIT INVARIANT:
        // Total Inbound (1000) - Total Outbound (100) = Total Remaining in all bins (900)
        $totalInbound = (float) InventoryMovement::where('material_lot_id', $lot->id)
            ->where('movement_type', InventoryMovement::TYPE_RECEIPT)
            ->sum('quantity');

        $totalOutbound = (float) InventoryMovement::where('material_lot_id', $lot->id)
            ->where('movement_type', InventoryMovement::TYPE_SHIPMENT)
            ->sum('quantity');

        $totalPhysicalStock = (float) InventoryBalance::where('material_lot_id', $lot->id)->sum('quantity_on_hand');

        $this->assertEquals(1000.0, $totalInbound);
        $this->assertEquals(100.0, $totalOutbound);
        $this->assertEquals(900.0, $totalPhysicalStock);
        $this->assertEquals(0.0, ($totalInbound - $totalOutbound) - $totalPhysicalStock);

        // Detailed bin breakdown:
        // Dock: 1000 - 400 (putaway) - 100 (shipment) = 500
        $dockStock = (float) InventoryBalance::where('bin_id', $dockBin->id)->where('material_lot_id', $lot->id)->value('quantity_on_hand');
        $this->assertEquals(500.0, $dockStock);

        // Rack: 400 - 250 (staged) = 150
        $rackStock = (float) InventoryBalance::where('bin_id', $rackBin->id)->where('material_lot_id', $lot->id)->value('quantity_on_hand');
        $this->assertEquals(150.0, $rackStock);

        // Staging: 250
        $stgStock = (float) InventoryBalance::where('bin_id', $stgBin->id)->where('material_lot_id', $lot->id)->value('quantity_on_hand');
        $this->assertEquals(250.0, $stgStock);
    }

    public function test_concurrent_allocation_prevents_negative_balance_and_over_reservation(): void
    {
        $movementService = app(InventoryMovementService::class);
        $fifoService = app(FifoAllocationService::class);

        $item = Item::where('sku', 'RAW-ABS-HI-121')->firstOrFail();
        $bin = WarehouseBin::where('bin_code', 'RM-A-01-11')->firstOrFail();

        $lot = MaterialLot::create([
            'lot_number' => 'LOT-STRESS-TEST',
            'item_id' => $item->id,
            'expiry_date' => now()->addMonths(6),
            'status' => MaterialLot::STATUS_AVAILABLE,
            'received_at' => now()->subDays(2),
        ]);

        // Stock exactly 100 kg
        $movementService->recordReceipt($item, $lot, $bin, 100.0);

        $successfulAllocations = 0;
        $failedAllocations = 0;

        // Simulate 7 successive attempts to allocate 20 kg each (Total demand: 140 kg, available: 100 kg)
        for ($i = 0; $i < 7; $i++) {
            try {
                $fifoService->allocate($item, 20.0);
                $successfulAllocations++;
            } catch (InsufficientStockException $e) {
                $failedAllocations++;
            }
        }

        // Exactly 5 allocations must succeed (5 * 20 = 100 kg)
        // Exactly 2 must fail due to insufficient stock
        $this->assertEquals(5, $successfulAllocations);
        $this->assertEquals(2, $failedAllocations);

        // Verify remaining available quantity is 0, not negative
        $balance = InventoryBalance::where('material_lot_id', $lot->id)->firstOrFail();
        $this->assertEquals(100.0, (float) $balance->quantity_on_hand);
        $this->assertEquals(100.0, (float) $balance->quantity_reserved);
        $this->assertEquals(0.0, $balance->available_quantity);
    }

    public function test_reservation_release_restores_exact_available_balance(): void
    {
        $movementService = app(InventoryMovementService::class);
        $fifoService = app(FifoAllocationService::class);

        $item = Item::where('sku', 'RAW-PC-OPT-300')->firstOrFail();
        $bin = WarehouseBin::where('bin_code', 'RM-A-01-11')->firstOrFail();

        $lot = MaterialLot::create([
            'lot_number' => 'LOT-RELEASE-TEST',
            'item_id' => $item->id,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'received_at' => now()->subDay(),
        ]);

        $movementService->recordReceipt($item, $lot, $bin, 500.0);

        // Allocate 150 kg
        $allocations = $fifoService->allocate($item, 150.0);
        $this->assertCount(1, $allocations);

        $balance = InventoryBalance::where('material_lot_id', $lot->id)->firstOrFail();
        $this->assertEquals(150.0, (float) $balance->quantity_reserved);
        $this->assertEquals(350.0, $balance->available_quantity);

        // Release 150 kg
        $fifoService->releaseReservation($allocations[0]['balance_id'], 150.0);

        $balance->refresh();
        $this->assertEquals(0.0, (float) $balance->quantity_reserved);
        $this->assertEquals(500.0, $balance->available_quantity);
    }

    public function test_skip_locked_allocates_multiple_bins_in_fifo_order(): void
    {
        $movementService = app(InventoryMovementService::class);
        $fifoService = app(FifoAllocationService::class);

        $item = Item::where('sku', 'RAW-PP-INJ-503')->firstOrFail();
        $bin1 = WarehouseBin::where('bin_code', 'RM-A-01-11')->firstOrFail();
        $bin2 = WarehouseBin::where('bin_code', 'RM-A-01-12')->firstOrFail();

        // Lot A received 5 days ago (older)
        $lotA = MaterialLot::create([
            'lot_number' => 'LOT-PP-OLDER',
            'item_id' => $item->id,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'received_at' => now()->subDays(5),
            'lot_type' => MaterialLot::TYPE_VIRGIN,
        ]);
        $movementService->recordReceipt($item, $lotA, $bin1, 200.0);

        // Lot B received 1 day ago (newer)
        $lotB = MaterialLot::create([
            'lot_number' => 'LOT-PP-NEWER',
            'item_id' => $item->id,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'received_at' => now()->subDays(1),
            'lot_type' => MaterialLot::TYPE_VIRGIN,
        ]);
        $movementService->recordReceipt($item, $lotB, $bin2, 200.0);

        // Allocate 250 kg (should consume 200 from Lot A first, then 50 from Lot B)
        $allocations = $fifoService->allocate($item, 250.0);

        $this->assertCount(2, $allocations);
        $this->assertEquals('LOT-PP-OLDER', $allocations[0]['lot_number']);
        $this->assertEquals(200.0, $allocations[0]['quantity']);
        $this->assertEquals('LOT-PP-NEWER', $allocations[1]['lot_number']);
        $this->assertEquals(50.0, $allocations[1]['quantity']);

        // Check balances
        $balA = InventoryBalance::where('material_lot_id', $lotA->id)->firstOrFail();
        $balB = InventoryBalance::where('material_lot_id', $lotB->id)->firstOrFail();
        $this->assertEquals(200.0, (float) $balA->quantity_reserved);
        $this->assertEquals(0.0, $balA->available_quantity);
        $this->assertEquals(50.0, (float) $balB->quantity_reserved);
        $this->assertEquals(150.0, $balB->available_quantity);
    }
}
