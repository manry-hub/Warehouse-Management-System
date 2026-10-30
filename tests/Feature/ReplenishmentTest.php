<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\ReplenishmentTask;
use App\Models\User;
use App\Models\WarehouseBin;
use App\Services\ReplenishmentService;
use Database\Seeders\ItemCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WarehouseTopologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReplenishmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(WarehouseTopologySeeder::class);
        $this->seed(ItemCatalogSeeder::class);
    }

    protected function setupPickAndBulkStock(): array
    {
        $item = Item::firstOrFail();

        // 1. Target Pick-Face Bin
        $pickBin = WarehouseBin::where('aisle', 'A')->firstOrFail();
        $pickBin->update([
            'is_pick_face' => true,
            'min_stock_kg' => 100.000,
            'max_stock_kg' => 300.000,
        ]);

        // Initial pick-face stock: only 20 KG (below 100 KG min!)
        $oldLot = MaterialLot::create([
            'item_id' => $item->id,
            'lot_number' => 'LOT-PICK-INITIAL',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 20.0,
            'remaining_quantity' => 20.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'manufactured_date' => now()->subDays(12),
            'expiry_date' => now()->addMonths(6),
        ]);

        InventoryBalance::create([
            'bin_id' => $pickBin->id,
            'item_id' => $item->id,
            'material_lot_id' => $oldLot->id,
            'quantity_on_hand' => 20.0,
            'quantity_reserved' => 0.0,
        ]);

        // 2. Source Bulk Bin (non-pick-face)
        $bulkBin = WarehouseBin::where('id', '!=', $pickBin->id)->where('is_pick_face', false)->firstOrFail();

        $bulkLot = MaterialLot::create([
            'item_id' => $item->id,
            'lot_number' => 'LOT-BULK-RESERVE-01',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 1000.0,
            'remaining_quantity' => 1000.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'manufactured_date' => now()->subDays(4),
            'expiry_date' => now()->addMonths(12),
        ]);

        $bulkBalance = InventoryBalance::create([
            'bin_id' => $bulkBin->id,
            'item_id' => $item->id,
            'material_lot_id' => $bulkLot->id,
            'quantity_on_hand' => 500.0,
            'quantity_reserved' => 0.0,
        ]);

        return compact('item', 'pickBin', 'bulkBin', 'oldLot', 'bulkLot', 'bulkBalance');
    }

    public function test_evaluate_deficits_detects_below_minimum_pick_bins_and_reserves_fefo_bulk_stock(): void
    {
        $data = $this->setupPickAndBulkStock();
        $service = app(ReplenishmentService::class);

        $spawned = $service->evaluateDeficits();

        $this->assertCount(1, $spawned);
        $task = $spawned->first();

        $this->assertEquals(ReplenishmentTask::STATUS_PENDING, $task->status);
        // Effective stock was 20 KG (< 50% of 100 KG min), so priority is URGENT
        $this->assertEquals(ReplenishmentTask::PRIORITY_URGENT, $task->priority);
        $this->assertEquals($data['pickBin']->id, $task->target_bin_id);
        $this->assertEquals($data['bulkBin']->id, $task->source_bin_id);
        $this->assertEquals($data['item']->id, $task->item_id);
        $this->assertEquals($data['bulkLot']->id, $task->material_lot_id);

        // Expected deficit: Max 300 - Effective 20 = 280 KG
        $this->assertEquals(280.0, (float) $task->requested_quantity_kg);

        // Verify bulk reserve balance is locked/reserved
        $bulkBalance = $data['bulkBalance']->fresh();
        $this->assertEquals(280.0, (float) $bulkBalance->quantity_reserved);

        // Idempotency check: Calling evaluate again must NOT spawn duplicate tasks
        $spawnedSecond = $service->evaluateDeficits();
        $this->assertCount(0, $spawnedSecond);
    }

    public function test_artisan_evaluate_replenishments_command_executes_cleanly(): void
    {
        $this->setupPickAndBulkStock();

        $this->artisan('wms:evaluate-replenishments')
            ->expectsOutputToContain('Generated 1 replenishment missions')
            ->assertExitCode(0);
    }

    public function test_scanner_replenishment_api_and_forklift_confirmation_with_ledger_movement(): void
    {
        $data = $this->setupPickAndBulkStock();
        $service = app(ReplenishmentService::class);
        $spawned = $service->evaluateDeficits();
        $task = $spawned->first();

        $user = User::where('email', 'handler@wms.local')->firstOrFail();
        Sanctum::actingAs($user, ['scanner:access']);

        // 1. Pending tasks list
        $response = $this->getJson('/api/v1/scanner/replenishments/pending');
        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['task_number' => $task->task_number]);

        // 2. Start mission
        $startRes = $this->postJson("/api/v1/scanner/replenishments/{$task->id}/start");
        $startRes->assertStatus(200)
            ->assertJson(['success' => true]);

        $task->refresh();
        $this->assertEquals(ReplenishmentTask::STATUS_IN_TRANSIT, $task->status);
        $this->assertEquals($user->id, $task->assigned_to);

        // 3. Validation failure checks
        // Wrong source bulk bin
        $wrongSourceRes = $this->postJson("/api/v1/scanner/replenishments/{$task->id}/confirm", [
            'scanned_source_bin' => 'WRONG-BIN-01',
            'scanned_lot_number' => $data['bulkLot']->lot_number,
            'scanned_target_bin' => $data['pickBin']->bin_code,
            'quantity_kg' => 280.0,
        ]);
        $wrongSourceRes->assertStatus(422)
            ->assertJson(['success' => false]);

        // Wrong lot
        $wrongLotRes = $this->postJson("/api/v1/scanner/replenishments/{$task->id}/confirm", [
            'scanned_source_bin' => $data['bulkBin']->bin_code,
            'scanned_lot_number' => 'WRONG-LOT-999',
            'scanned_target_bin' => $data['pickBin']->bin_code,
            'quantity_kg' => 280.0,
        ]);
        $wrongLotRes->assertStatus(422)
            ->assertJson(['success' => false]);

        // Wrong target pick bin
        $wrongTargetRes = $this->postJson("/api/v1/scanner/replenishments/{$task->id}/confirm", [
            'scanned_source_bin' => $data['bulkBin']->bin_code,
            'scanned_lot_number' => $data['bulkLot']->lot_number,
            'scanned_target_bin' => 'WRONG-TARGET-BIN',
            'quantity_kg' => 280.0,
        ]);
        $wrongTargetRes->assertStatus(422)
            ->assertJson(['success' => false]);

        // 4. Valid confirmation
        $initialPickBinStock = (float) InventoryBalance::where('bin_id', $data['pickBin']->id)
            ->sum('quantity_on_hand');
        $initialBulkStock = (float) InventoryBalance::where('bin_id', $data['bulkBin']->id)
            ->sum('quantity_on_hand');

        $confirmRes = $this->postJson("/api/v1/scanner/replenishments/{$task->id}/confirm", [
            'scanned_source_bin' => $data['bulkBin']->bin_code,
            'scanned_lot_number' => $data['bulkLot']->lot_number,
            'scanned_target_bin' => $data['pickBin']->bin_code,
            'quantity_kg' => 280.0,
        ]);

        $confirmRes->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify task updated
        $task->refresh();
        $this->assertEquals(ReplenishmentTask::STATUS_COMPLETED, $task->status);
        $this->assertEquals(280.0, (float) $task->replenished_quantity_kg);
        $this->assertNotNull($task->completed_at);

        // Verify double-entry InventoryMovement created
        $movement = InventoryMovement::where('movement_type', InventoryMovement::TYPE_REPLENISHMENT)
            ->where('reference_id', $task->task_number)
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(280.0, (float) $movement->quantity);
        $this->assertEquals($data['bulkBin']->id, $movement->source_bin_id);
        $this->assertEquals($data['pickBin']->id, $movement->destination_bin_id);

        // Verify balances:
        // Bulk bin decremented by 280, reservation relieved
        $finalBulk = $data['bulkBalance']->fresh();
        $this->assertEquals($initialBulkStock - 280.0, (float) $finalBulk->quantity_on_hand);
        $this->assertEquals(0.0, (float) $finalBulk->quantity_reserved);

        // Target pick face bin incremented by 280
        $finalPickStock = (float) InventoryBalance::where('bin_id', $data['pickBin']->id)
            ->sum('quantity_on_hand');
        $this->assertEquals($initialPickBinStock + 280.0, $finalPickStock);
    }

    public function test_cancel_replenishment_task_releases_bulk_reservation(): void
    {
        $data = $this->setupPickAndBulkStock();
        $service = app(ReplenishmentService::class);
        $spawned = $service->evaluateDeficits();
        $task = $spawned->first();

        $bulkBalance = $data['bulkBalance']->fresh();
        $this->assertEquals(280.0, (float) $bulkBalance->quantity_reserved);

        $service->cancelTask($task, 'Operator error');

        $task->refresh();
        $this->assertEquals(ReplenishmentTask::STATUS_CANCELLED, $task->status);

        $bulkBalance->refresh();
        $this->assertEquals(0.0, (float) $bulkBalance->quantity_reserved);
    }
}
