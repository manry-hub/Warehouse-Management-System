<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\CycleCountItem;
use App\Models\CycleCountOrder;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\WarehouseZone;
use App\Services\CycleCountService;
use App\Services\InventoryMovementService;
use Database\Seeders\ItemCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WarehouseTopologySeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CycleCountTest extends TestCase
{
    use RefreshDatabase;

    protected CycleCountService $cycleCountService;

    protected InventoryMovementService $movementService;

    protected Warehouse $warehouse;

    protected WarehouseZone $zone;

    protected WarehouseBin $bin1;

    protected WarehouseBin $bin2;

    protected Item $resinItem;

    protected MaterialLot $lot1;

    protected MaterialLot $lot2;

    protected User $handlerUser;

    protected User $managerUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(WarehouseTopologySeeder::class);
        $this->seed(ItemCatalogSeeder::class);

        $this->movementService = app(InventoryMovementService::class);
        $this->cycleCountService = app(CycleCountService::class);

        $this->warehouse = Warehouse::where('code', 'WH-MAIN')->firstOrFail();
        $this->zone = WarehouseZone::where('code', 'ZONE-RM')->firstOrFail();
        $this->bin1 = WarehouseBin::where('bin_code', 'RM-A-01-11')->firstOrFail();
        $this->bin2 = WarehouseBin::where('bin_code', 'RM-A-01-12')->firstOrFail();
        $this->resinItem = Item::where('sku', 'RAW-PP-INJ-503')->firstOrFail();

        $this->handlerUser = User::where('email', 'handler@wms.local')->firstOrFail();
        $this->managerUser = User::where('email', 'admin@wms.local')->firstOrFail();

        // Create two test lots and deposit initial stock via receipts
        $this->lot1 = MaterialLot::create([
            'lot_number' => 'LOT-TEST-CC-001',
            'item_id' => $this->resinItem->id,
            'vendor_code' => 'VEND-LOTTE',
            'vendor_lot_number' => 'V-1001',
            'vendor_heat_number' => 'H-1001',
            'manufactured_date' => now()->subDays(10),
            'expiry_date' => now()->addYear(),
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);

        $this->lot2 = MaterialLot::create([
            'lot_number' => 'LOT-TEST-CC-002',
            'item_id' => $this->resinItem->id,
            'vendor_code' => 'VEND-LOTTE',
            'vendor_lot_number' => 'V-1002',
            'vendor_heat_number' => 'H-1002',
            'manufactured_date' => now()->subDays(5),
            'expiry_date' => now()->addYear(),
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);

        $this->movementService->recordReceipt(
            item: $this->resinItem,
            lot: $this->lot1,
            destinationBin: $this->bin1,
            quantity: 500.0,
            referenceId: 'PO-INIT-001',
            user: $this->handlerUser
        );

        $this->movementService->recordReceipt(
            item: $this->resinItem,
            lot: $this->lot2,
            destinationBin: $this->bin2,
            quantity: 300.0,
            referenceId: 'PO-INIT-002',
            user: $this->handlerUser
        );
    }

    public function test_can_create_and_start_cycle_count_with_frozen_snapshot(): void
    {
        $order = $this->cycleCountService->createOrder(
            warehouse: $this->warehouse,
            zone: $this->zone,
            countType: CycleCountOrder::TYPE_BLIND,
            assignedTo: $this->handlerUser,
            notes: 'Monthly Resin Stock Opname'
        );

        $this->assertEquals(CycleCountOrder::STATUS_DRAFT, $order->status);
        $this->assertStringStartsWith('CC-', $order->count_number);

        // Start count - should freeze balance snapshot
        $startedOrder = $this->cycleCountService->startCount($order);

        $this->assertEquals(CycleCountOrder::STATUS_IN_PROGRESS, $startedOrder->status);
        $this->assertNotNull($startedOrder->snapshot_at);
        $this->assertCount(2, $startedOrder->items);

        $item1 = $startedOrder->items->firstWhere('material_lot_id', $this->lot1->id);
        $this->assertNotNull($item1);
        $this->assertEquals(500.0, (float) $item1->recorded_quantity);
        $this->assertNull($item1->counted_quantity);
        $this->assertEquals(CycleCountItem::STATUS_PENDING, $item1->status);
    }

    public function test_mobile_scanner_sheet_enforces_blind_count_secrecy(): void
    {
        $order = $this->cycleCountService->createOrder(
            warehouse: $this->warehouse,
            zone: $this->zone,
            countType: CycleCountOrder::TYPE_BLIND,
            assignedTo: $this->handlerUser
        );
        $this->cycleCountService->startCount($order);

        Sanctum::actingAs($this->handlerUser, ['scanner:access']);

        $response = $this->getJson("/api/v1/scanner/cycle-counts/{$order->id}/sheet");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $order->id,
                    'count_number' => $order->count_number,
                    'status' => CycleCountOrder::STATUS_IN_PROGRESS,
                ],
            ]);

        $responseData = $response->json('data.items');
        $this->assertCount(2, $responseData);

        // CRITICAL AUDIT VERIFICATION:
        // Neither recorded_quantity nor variance_quantity must ever be present in the blind sheet payload!
        foreach ($responseData as $item) {
            $this->assertArrayNotHasKey('recorded_quantity', $item, 'Blind count sheet must not reveal book balance.');
            $this->assertArrayNotHasKey('variance_quantity', $item, 'Blind count sheet must not reveal variance.');
            $this->assertArrayHasKey('bin_code', $item);
            $this->assertArrayHasKey('lot_number', $item);
            $this->assertArrayHasKey('item_sku', $item);
        }
    }

    public function test_scanner_submits_count_and_computes_variances_correctly(): void
    {
        $order = $this->cycleCountService->createOrder(
            warehouse: $this->warehouse,
            zone: $this->zone,
            assignedTo: $this->handlerUser
        );
        $this->cycleCountService->startCount($order);

        Sanctum::actingAs($this->handlerUser, ['scanner:access']);

        // Bin 1: Recorded 500 KG -> Counted 525 KG (Surplus +25 KG)
        $resp1 = $this->postJson("/api/v1/scanner/cycle-counts/{$order->id}/count", [
            'bin_code' => $this->bin1->bin_code,
            'lot_number' => $this->lot1->lot_number,
            'counted_quantity' => 525.0,
            'notes' => 'Found 1 extra 25kg bag',
        ]);

        $resp1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'counted_quantity' => 525.0,
                    'item_status' => CycleCountItem::STATUS_COUNTED,
                ],
            ]);

        // Bin 2: Recorded 300 KG -> Counted 275 KG (Shortage -25 KG)
        $resp2 = $this->postJson("/api/v1/scanner/cycle-counts/{$order->id}/count", [
            'bin_code' => $this->bin2->bin_code,
            'lot_number' => $this->lot2->lot_number,
            'counted_quantity' => 275.0,
            'notes' => '1 bag missing from pallet',
        ]);

        $resp2->assertStatus(200);

        // Because there are variances, order should transition to REVIEW
        $order->refresh();
        $this->assertEquals(CycleCountOrder::STATUS_REVIEW, $order->status);

        $item1 = $order->items()->where('material_lot_id', $this->lot1->id)->firstOrFail();
        $this->assertEquals(25.0, (float) $item1->variance_quantity);
        $this->assertTrue($item1->isSurplus());

        $item2 = $order->items()->where('material_lot_id', $this->lot2->id)->firstOrFail();
        $this->assertEquals(-25.0, (float) $item2->variance_quantity);
        $this->assertTrue($item2->isShortage());
    }

    public function test_exact_match_transitions_order_to_counted(): void
    {
        $order = $this->cycleCountService->createOrder(
            warehouse: $this->warehouse,
            zone: $this->zone,
            assignedTo: $this->handlerUser
        );
        $this->cycleCountService->startCount($order);

        // Count exact match for both items
        $this->cycleCountService->recordCount($order, $this->bin1->id, $this->lot1->id, 500.0);
        $this->cycleCountService->recordCount($order, $this->bin2->id, $this->lot2->id, 300.0);

        $order->refresh();
        $this->assertEquals(CycleCountOrder::STATUS_COUNTED, $order->status);

        $item1 = $order->items()->where('material_lot_id', $this->lot1->id)->firstOrFail();
        $this->assertTrue($item1->isMatch());
        $this->assertEquals(0.0, (float) $item1->variance_quantity);
    }

    public function test_recount_request_resets_item_and_order_status(): void
    {
        $order = $this->cycleCountService->createOrder(
            warehouse: $this->warehouse,
            zone: $this->zone,
            assignedTo: $this->handlerUser
        );
        $this->cycleCountService->startCount($order);
        $this->cycleCountService->recordCount($order, $this->bin1->id, $this->lot1->id, 550.0);
        $this->cycleCountService->recordCount($order, $this->bin2->id, $this->lot2->id, 300.0);

        $order->refresh();
        $this->assertEquals(CycleCountOrder::STATUS_REVIEW, $order->status);

        $item1 = $order->items()->where('material_lot_id', $this->lot1->id)->firstOrFail();
        $this->cycleCountService->requestRecount($item1, 'Variance too large');

        $item1->refresh();
        $this->assertEquals(CycleCountItem::STATUS_RECOUNT_REQUESTED, $item1->status);
        $this->assertNull($item1->counted_quantity);
        $this->assertNull($item1->variance_quantity);

        $order->refresh();
        $this->assertEquals(CycleCountOrder::STATUS_IN_PROGRESS, $order->status);
    }

    public function test_supervisor_approval_and_atomic_ledger_adjustment_posting(): void
    {
        $order = $this->cycleCountService->createOrder(
            warehouse: $this->warehouse,
            zone: $this->zone,
            assignedTo: $this->handlerUser
        );
        $this->cycleCountService->startCount($order);

        // Bin 1: 500 -> 520 (+20 surplus)
        $this->cycleCountService->recordCount($order, $this->bin1->id, $this->lot1->id, 520.0);
        // Bin 2: 300 -> 280 (-20 shortage)
        $this->cycleCountService->recordCount($order, $this->bin2->id, $this->lot2->id, 280.0);

        $order->refresh();

        // Posting directly before approval must fail
        $this->expectException(DomainException::class);
        $this->cycleCountService->postAdjustments($order, $this->managerUser);
    }

    public function test_post_adjustments_commits_double_entry_ledger_and_updates_balances(): void
    {
        $order = $this->cycleCountService->createOrder(
            warehouse: $this->warehouse,
            zone: $this->zone,
            assignedTo: $this->handlerUser
        );
        $this->cycleCountService->startCount($order);

        // Bin 1: 500 -> 525 (+25 surplus)
        $this->cycleCountService->recordCount($order, $this->bin1->id, $this->lot1->id, 525.0);
        // Bin 2: 300 -> 290 (-10 shortage)
        $this->cycleCountService->recordCount($order, $this->bin2->id, $this->lot2->id, 290.0);

        // Supervisor approves
        $this->cycleCountService->approveOrder($order, $this->managerUser);
        $order->refresh();
        $this->assertEquals(CycleCountOrder::STATUS_APPROVED, $order->status);

        // Post adjustments
        $result = $this->cycleCountService->postAdjustments($order, $this->managerUser);

        $this->assertEquals(2, $result['movements_recorded']);
        $this->assertEquals(25.0, $result['total_surplus']);
        $this->assertEquals(10.0, $result['total_shortage']);

        $order->refresh();
        $this->assertEquals(CycleCountOrder::STATUS_POSTED, $order->status);
        $this->assertNotNull($order->posted_at);

        // Verify InventoryMovements
        $movements = InventoryMovement::where('reference_type', 'CYCLE_COUNT_ORDER')
            ->where('reference_id', $order->count_number)
            ->get();

        $this->assertCount(2, $movements);

        // Surplus movement: source is NULL, destination is Bin 1
        $surplusMovement = $movements->firstWhere('material_lot_id', $this->lot1->id);
        $this->assertNotNull($surplusMovement);
        $this->assertNull($surplusMovement->source_bin_id);
        $this->assertEquals($this->bin1->id, $surplusMovement->destination_bin_id);
        $this->assertEquals(25.0, (float) $surplusMovement->quantity);

        // Shortage movement: source is Bin 2, destination is NULL
        $shortageMovement = $movements->firstWhere('material_lot_id', $this->lot2->id);
        $this->assertNotNull($shortageMovement);
        $this->assertEquals($this->bin2->id, $shortageMovement->source_bin_id);
        $this->assertNull($shortageMovement->destination_bin_id);
        $this->assertEquals(10.0, (float) $shortageMovement->quantity);

        // Verify updated InventoryBalances
        $balance1 = InventoryBalance::where('bin_id', $this->bin1->id)
            ->where('material_lot_id', $this->lot1->id)
            ->firstOrFail();
        $this->assertEquals(525.0, (float) $balance1->quantity_on_hand, 'Balance 1 must match counted physical stock');

        $balance2 = InventoryBalance::where('bin_id', $this->bin2->id)
            ->where('material_lot_id', $this->lot2->id)
            ->firstOrFail();
        $this->assertEquals(290.0, (float) $balance2->quantity_on_hand, 'Balance 2 must match counted physical stock');
    }

    public function test_cannot_post_negative_adjustment_if_stock_became_insufficient(): void
    {
        $order = $this->cycleCountService->createOrder(
            warehouse: $this->warehouse,
            zone: $this->zone,
            assignedTo: $this->handlerUser
        );
        $this->cycleCountService->startCount($order);

        // Bin 1: Exact match 500 KG
        $this->cycleCountService->recordCount($order, $this->bin1->id, $this->lot1->id, 500.0);
        // Bin 2: Shortage of -100 KG (Recorded 300, Counted 200)
        $this->cycleCountService->recordCount($order, $this->bin2->id, $this->lot2->id, 200.0);
        $order->refresh();
        $this->cycleCountService->approveOrder($order, $this->managerUser);

        // Concurrently deplete bin2 stock before posting
        InventoryBalance::where('bin_id', $this->bin2->id)
            ->where('material_lot_id', $this->lot2->id)
            ->update(['quantity_on_hand' => 50.0]); // Less than 100 KG shortage

        $this->expectException(InsufficientStockException::class);
        $this->cycleCountService->postAdjustments($order, $this->managerUser);
    }
}
