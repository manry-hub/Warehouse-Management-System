<?php

namespace Tests\Feature;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\PickingBatch;
use App\Models\PickingBatchItem;
use App\Models\PickingWave;
use App\Models\User;
use App\Models\WarehouseBin;
use App\Services\WavePickingService;
use Database\Seeders\ItemCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WarehouseTopologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WaveBatchPickingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(WarehouseTopologySeeder::class);
        $this->seed(ItemCatalogSeeder::class);
    }

    protected function setupStockAndOrders(): array
    {
        $item = Item::firstOrFail();
        $binA1 = WarehouseBin::where('aisle', 'A')->firstOrFail();
        $binA2 = WarehouseBin::where('aisle', 'A')->where('id', '!=', $binA1->id)->firstOrFail();

        $lot1 = MaterialLot::create([
            'item_id' => $item->id,
            'lot_number' => 'LOT-WAVE-001',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 500.0,
            'remaining_quantity' => 500.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'received_at' => now(),
            'manufactured_date' => now()->subDays(5),
            'expiry_date' => now()->addMonths(12),
        ]);

        $lot2 = MaterialLot::create([
            'item_id' => $item->id,
            'lot_number' => 'LOT-WAVE-002',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 500.0,
            'remaining_quantity' => 500.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'received_at' => now(),
            'manufactured_date' => now()->subDays(2),
            'expiry_date' => now()->addMonths(12),
        ]);

        // Place stock balances
        InventoryBalance::create([
            'bin_id' => $binA1->id,
            'item_id' => $item->id,
            'material_lot_id' => $lot1->id,
            'quantity_on_hand' => 200.0,
            'quantity_reserved' => 0.0,
        ]);

        InventoryBalance::create([
            'bin_id' => $binA2->id,
            'item_id' => $item->id,
            'material_lot_id' => $lot2->id,
            'quantity_on_hand' => 200.0,
            'quantity_reserved' => 0.0,
        ]);

        // Create 2 Delivery Orders
        $do1 = DeliveryOrder::create([
            'do_number' => 'DO-WAVE-01',
            'customer_name' => 'Toyota Motor Manufacturing',
            'shipping_address' => 'Plant Karawang Bay 2',
            'vehicle_plate_number' => 'B 1234 TOY',
            'driver_name' => 'Budi Santoso',
            'status' => DeliveryOrder::STATUS_DRAFT,
            'total_cartons' => 2,
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $do1->id,
            'item_id' => $item->id,
            'material_lot_id' => $lot1->id,
            'source_bin_id' => $binA1->id,
            'quantity_ordered' => 25.0,
            'quantity_loaded' => 0,
        ]);

        $do2 = DeliveryOrder::create([
            'do_number' => 'DO-WAVE-02',
            'customer_name' => 'Astra Daihatsu Motor',
            'shipping_address' => 'Plant Sunter Bay 1',
            'vehicle_plate_number' => 'B 5678 ADM',
            'driver_name' => 'Agus Priyono',
            'status' => DeliveryOrder::STATUS_DRAFT,
            'total_cartons' => 2,
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $do2->id,
            'item_id' => $item->id,
            'material_lot_id' => $lot2->id,
            'source_bin_id' => $binA2->id,
            'quantity_ordered' => 30.0,
            'quantity_loaded' => 0,
        ]);

        return compact('item', 'binA1', 'binA2', 'lot1', 'lot2', 'do1', 'do2');
    }

    public function test_wave_creation_and_release_generates_batches_with_totes_and_spath(): void
    {
        $data = $this->setupStockAndOrders();

        $service = app(WavePickingService::class);

        $wave = $service->createWaveFromDeliveryOrders([
            $data['do1']->id,
            $data['do2']->id,
        ], [
            'priority' => PickingWave::PRIORITY_URGENT,
        ]);

        $this->assertEquals(PickingWave::STATUS_DRAFT, $wave->status);
        $this->assertEquals(2, $wave->total_orders);
        $this->assertEquals(2, $wave->total_lines);

        // Release wave
        $service->releaseWave($wave, maxOrdersPerBatch: 4);

        $wave->refresh();
        $this->assertEquals(PickingWave::STATUS_RELEASED, $wave->status);
        $this->assertNotNull($wave->released_at);

        // Verify batch generation
        $batches = $wave->batches()->with('items')->get();
        $this->assertCount(1, $batches);

        $batch = $batches->first();
        $this->assertEquals(PickingBatch::STATUS_ASSIGNED, $batch->status);
        $this->assertEquals(2, $batch->total_picks);

        // Check tote assignment: TOTE-01 and TOTE-02
        $items = $batch->items;
        $totes = $items->pluck('target_tote')->all();
        $this->assertContains('TOTE-01', $totes);
        $this->assertContains('TOTE-02', $totes);

        // Check S-Path pick sequences: 1, 2
        $sequences = $items->pluck('pick_sequence')->all();
        $this->assertEquals([1, 2], $sequences);
    }

    public function test_scanner_batch_endpoints_and_pick_confirmation_with_ledger_integrity(): void
    {
        $data = $this->setupStockAndOrders();
        $service = app(WavePickingService::class);
        $wave = $service->createWaveFromDeliveryOrders([$data['do1']->id, $data['do2']->id]);
        $service->releaseWave($wave);

        $batch = $wave->batches()->first();
        $firstItem = $batch->items()->orderBy('pick_sequence')->first();

        $user = User::where('email', 'handler@wms.local')->firstOrFail();
        Sanctum::actingAs($user, ['scanner:access']);

        // 1. Check active batches API
        $response = $this->getJson('/api/v1/scanner/batch-picking/active');
        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['batch_number' => $batch->batch_number]);

        // 2. Check batch tasks API
        $tasksRes = $this->getJson("/api/v1/scanner/batch-picking/{$batch->id}/tasks");
        $tasksRes->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('current_task.id', $firstItem->id);

        // 3. Negative check: Wrong bin fails
        $wrongBinRes = $this->postJson('/api/v1/scanner/batch-picking/confirm', [
            'batch_item_id' => $firstItem->id,
            'scanned_bin' => 'WRONG-BIN-99',
            'scanned_lot' => $firstItem->materialLot->lot_number,
            'scanned_tote' => $firstItem->target_tote,
            'quantity' => (float) $firstItem->quantity_to_pick,
        ]);
        $wrongBinRes->assertStatus(422)
            ->assertJson(['success' => false]);

        // 4. Negative check: Wrong lot fails
        $wrongLotRes = $this->postJson('/api/v1/scanner/batch-picking/confirm', [
            'batch_item_id' => $firstItem->id,
            'scanned_bin' => $firstItem->bin->bin_code,
            'scanned_lot' => 'WRONG-LOT-000',
            'scanned_tote' => $firstItem->target_tote,
            'quantity' => (float) $firstItem->quantity_to_pick,
        ]);
        $wrongLotRes->assertStatus(422)
            ->assertJson(['success' => false]);

        // 5. Negative check: Wrong tote drop fails
        $wrongToteRes = $this->postJson('/api/v1/scanner/batch-picking/confirm', [
            'batch_item_id' => $firstItem->id,
            'scanned_bin' => $firstItem->bin->bin_code,
            'scanned_lot' => $firstItem->materialLot->lot_number,
            'scanned_tote' => 'TOTE-99',
            'quantity' => (float) $firstItem->quantity_to_pick,
        ]);
        $wrongToteRes->assertStatus(422)
            ->assertJson(['success' => false]);

        // 6. Positive check: Valid pick confirmation
        $initialSourceQty = (float) InventoryBalance::where('bin_id', $firstItem->warehouse_bin_id)
            ->where('material_lot_id', $firstItem->material_lot_id)
            ->value('quantity_on_hand');

        $pickQty = (float) $firstItem->quantity_to_pick;

        $validRes = $this->postJson('/api/v1/scanner/batch-picking/confirm', [
            'batch_item_id' => $firstItem->id,
            'scanned_bin' => $firstItem->bin->bin_code,
            'scanned_lot' => $firstItem->materialLot->lot_number,
            'scanned_tote' => $firstItem->target_tote,
            'quantity' => $pickQty,
        ]);

        $validRes->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify Batch Item updated
        $firstItem->refresh();
        $this->assertEquals(PickingBatchItem::STATUS_PICKED, $firstItem->status);
        $this->assertEquals($pickQty, (float) $firstItem->quantity_picked);

        // Verify Double-Entry Movement recorded
        $movement = InventoryMovement::where('movement_type', InventoryMovement::TYPE_BATCH_PICK)
            ->where('reference_id', $batch->batch_number)
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals($pickQty, (float) $movement->quantity);
        $this->assertEquals($firstItem->warehouse_bin_id, $movement->source_bin_id);

        // Verify Source Inventory Balance decremented accurately
        $finalSourceQty = (float) InventoryBalance::where('bin_id', $firstItem->warehouse_bin_id)
            ->where('material_lot_id', $firstItem->material_lot_id)
            ->value('quantity_on_hand');
        $this->assertEquals($initialSourceQty - $pickQty, $finalSourceQty);

        // 7. Pick the remaining item to verify batch & wave auto-completion
        $secondItem = $batch->items()->where('status', PickingBatchItem::STATUS_PENDING)->firstOrFail();
        $completeRes = $this->postJson('/api/v1/scanner/batch-picking/confirm', [
            'batch_item_id' => $secondItem->id,
            'scanned_bin' => $secondItem->bin->bin_code,
            'scanned_lot' => $secondItem->materialLot->lot_number,
            'scanned_tote' => $secondItem->target_tote,
            'quantity' => (float) $secondItem->quantity_to_pick,
        ]);

        $completeRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'batch_completed' => true,
                'wave_completed' => true,
            ]);

        $batch->refresh();
        $wave->refresh();
        $this->assertEquals(PickingBatch::STATUS_COMPLETED, $batch->status);
        $this->assertEquals(PickingWave::STATUS_COMPLETED, $wave->status);
    }
}
