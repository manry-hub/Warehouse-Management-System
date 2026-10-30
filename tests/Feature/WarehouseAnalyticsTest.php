<?php

namespace Tests\Feature;

use App\Models\CycleCountItem;
use App\Models\CycleCountOrder;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\User;
use App\Models\WarehouseBin;
use App\Services\WarehouseAnalyticsService;
use Database\Seeders\ItemCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WarehouseTopologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(WarehouseTopologySeeder::class);
        $this->seed(ItemCatalogSeeder::class);
    }

    public function test_inventory_aging_breakdown_correctly_buckets_stock_by_age(): void
    {
        $item = Item::firstOrFail();
        $bin = WarehouseBin::firstOrFail();

        // 1. Fresh: 10 days old
        $lotFresh = MaterialLot::create([
            'item_id' => $item->id,
            'lot_number' => 'LOT-AGE-FRESH',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 100.0,
            'remaining_quantity' => 100.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'manufactured_date' => now()->subDays(10),
            'expiry_date' => now()->addYear(),
        ]);
        InventoryBalance::create([
            'bin_id' => $bin->id,
            'item_id' => $item->id,
            'material_lot_id' => $lotFresh->id,
            'quantity_on_hand' => 100.0,
            'quantity_reserved' => 0.0,
        ]);

        // 2. Normal: 45 days old
        $lotNormal = MaterialLot::create([
            'item_id' => $item->id,
            'lot_number' => 'LOT-AGE-NORMAL',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 200.0,
            'remaining_quantity' => 200.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'manufactured_date' => now()->subDays(45),
            'expiry_date' => now()->addYear(),
        ]);
        InventoryBalance::create([
            'bin_id' => $bin->id,
            'item_id' => $item->id,
            'material_lot_id' => $lotNormal->id,
            'quantity_on_hand' => 200.0,
            'quantity_reserved' => 0.0,
        ]);

        // 3. Warning: 75 days old
        $lotWarning = MaterialLot::create([
            'item_id' => $item->id,
            'lot_number' => 'LOT-AGE-WARN',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 50.0,
            'remaining_quantity' => 50.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'manufactured_date' => now()->subDays(75),
            'expiry_date' => now()->addYear(),
        ]);
        InventoryBalance::create([
            'bin_id' => $bin->id,
            'item_id' => $item->id,
            'material_lot_id' => $lotWarning->id,
            'quantity_on_hand' => 50.0,
            'quantity_reserved' => 0.0,
        ]);

        // 4. Critical: 110 days old
        $lotCritical = MaterialLot::create([
            'item_id' => $item->id,
            'lot_number' => 'LOT-AGE-CRIT',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 80.0,
            'remaining_quantity' => 80.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'manufactured_date' => now()->subDays(110),
            'expiry_date' => now()->addYear(),
        ]);
        InventoryBalance::create([
            'bin_id' => $bin->id,
            'item_id' => $item->id,
            'material_lot_id' => $lotCritical->id,
            'quantity_on_hand' => 80.0,
            'quantity_reserved' => 0.0,
        ]);

        // 5. Expired lot
        $lotExpired = MaterialLot::create([
            'item_id' => $item->id,
            'lot_number' => 'LOT-AGE-EXPIRED',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 30.0,
            'remaining_quantity' => 30.0,
            'status' => MaterialLot::STATUS_EXPIRED,
            'manufactured_date' => now()->subDays(200),
            'expiry_date' => now()->subDays(10),
        ]);
        InventoryBalance::create([
            'bin_id' => $bin->id,
            'item_id' => $item->id,
            'material_lot_id' => $lotExpired->id,
            'quantity_on_hand' => 30.0,
            'quantity_reserved' => 0.0,
        ]);

        /** @var WarehouseAnalyticsService $service */
        $service = app(WarehouseAnalyticsService::class);
        $aging = $service->getInventoryAgingBreakdown();

        $this->assertEquals(460.0, $aging['total_stock_kg']);
        $this->assertEquals(100.0, $aging['fresh_kg']);
        $this->assertEquals(200.0, $aging['normal_kg']);
        $this->assertEquals(500.0 / 10, $aging['warning_kg']); // 50.0
        $this->assertEquals(80.0, $aging['critical_kg']);
        $this->assertEquals(30.0, $aging['expired_kg']);
        $this->assertEquals(17.39, $aging['critical_percentage']); // (80 / 460) * 100
        $this->assertCount(5, $aging['lot_details']);
    }

    public function test_inventory_record_accuracy_calculates_ira_and_variances(): void
    {
        $item = Item::firstOrFail();
        $bins = WarehouseBin::take(4)->get();
        $this->assertCount(4, $bins);
        $user = User::factory()->create();

        $lot = MaterialLot::create([
            'item_id' => $item->id,
            'lot_number' => 'LOT-CC-AUDIT-01',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 100.0,
            'remaining_quantity' => 100.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'manufactured_date' => now()->subDays(5),
            'expiry_date' => now()->addYear(),
        ]);

        $order = CycleCountOrder::create([
            'count_number' => 'CC-ANALYTICS-01',
            'warehouse_id' => $bins[0]->zone->warehouse_id,
            'count_type' => CycleCountOrder::TYPE_BLIND,
            'status' => CycleCountOrder::STATUS_POSTED,
            'created_by' => $user->id,
        ]);

        // Item 1: Exact match (variance 0)
        CycleCountItem::create([
            'cycle_count_order_id' => $order->id,
            'item_id' => $item->id,
            'material_lot_id' => $lot->id,
            'bin_id' => $bins[0]->id,
            'recorded_quantity' => 50.0,
            'counted_quantity' => 50.0,
            'variance_quantity' => 0.0,
            'status' => CycleCountItem::STATUS_COUNTED,
            'counted_at' => now(),
        ]);

        // Item 2: Exact match (variance 0)
        CycleCountItem::create([
            'cycle_count_order_id' => $order->id,
            'item_id' => $item->id,
            'material_lot_id' => $lot->id,
            'bin_id' => $bins[1]->id,
            'recorded_quantity' => 100.0,
            'counted_quantity' => 100.0,
            'variance_quantity' => 0.0,
            'status' => CycleCountItem::STATUS_COUNTED,
            'counted_at' => now(),
        ]);

        // Item 3: Shrinkage loss (-5.0)
        CycleCountItem::create([
            'cycle_count_order_id' => $order->id,
            'item_id' => $item->id,
            'material_lot_id' => $lot->id,
            'bin_id' => $bins[2]->id,
            'recorded_quantity' => 40.0,
            'counted_quantity' => 35.0,
            'variance_quantity' => -5.0,
            'status' => CycleCountItem::STATUS_COUNTED,
            'counted_at' => now(),
        ]);

        // Item 4: Overage (+3.0)
        CycleCountItem::create([
            'cycle_count_order_id' => $order->id,
            'item_id' => $item->id,
            'material_lot_id' => $lot->id,
            'bin_id' => $bins[3]->id,
            'recorded_quantity' => 20.0,
            'counted_quantity' => 23.0,
            'variance_quantity' => 3.0,
            'status' => CycleCountItem::STATUS_COUNTED,
            'counted_at' => now(),
        ]);

        /** @var WarehouseAnalyticsService $service */
        $service = app(WarehouseAnalyticsService::class);
        $ira = $service->getInventoryRecordAccuracy();

        $this->assertEquals(4, $ira['total_audited']);
        $this->assertEquals(2, $ira['exact_matches']);
        $this->assertEquals(50.0, $ira['ira_percentage']);
        $this->assertEquals(-2.0, $ira['net_variance_kg']);
        $this->assertEquals(8.0, $ira['abs_variance_kg']);
        $this->assertCount(2, $ira['top_discrepancies']);
    }

    public function test_velocity_abc_classification_sorts_items_by_movement_frequency(): void
    {
        $items = Item::take(3)->get();
        $this->assertGreaterThanOrEqual(2, $items->count());

        $itemA = $items[0];
        $itemB = $items[1];

        $lotA = MaterialLot::create([
            'item_id' => $itemA->id,
            'lot_number' => 'LOT-ABC-A',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 100.0,
            'remaining_quantity' => 100.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'manufactured_date' => now()->subDays(5),
            'expiry_date' => now()->addYear(),
        ]);

        $lotB = MaterialLot::create([
            'item_id' => $itemB->id,
            'lot_number' => 'LOT-ABC-B',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 100.0,
            'remaining_quantity' => 100.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'manufactured_date' => now()->subDays(5),
            'expiry_date' => now()->addYear(),
        ]);

        // Item A: 10 movements
        for ($i = 0; $i < 10; $i++) {
            InventoryMovement::create([
                'transaction_uuid' => (string) Str::uuid(),
                'movement_type' => InventoryMovement::TYPE_PUTAWAY,
                'item_id' => $itemA->id,
                'material_lot_id' => $lotA->id,
                'quantity' => 10.0,
                'uom' => $itemA->unit_of_measure,
            ]);
        }

        // Item B: 2 movements
        for ($i = 0; $i < 2; $i++) {
            InventoryMovement::create([
                'transaction_uuid' => (string) Str::uuid(),
                'movement_type' => InventoryMovement::TYPE_PUTAWAY,
                'item_id' => $itemB->id,
                'material_lot_id' => $lotB->id,
                'quantity' => 5.0,
                'uom' => $itemB->unit_of_measure,
            ]);
        }

        /** @var WarehouseAnalyticsService $service */
        $service = app(WarehouseAnalyticsService::class);
        $velocity = $service->getVelocityAbcClassification();

        $this->assertEquals(12, $velocity['total_transactions']);
        $this->assertGreaterThanOrEqual(1, $velocity['class_a_count']);

        $itemMap = collect($velocity['items'])->keyBy('item_id');
        $this->assertEquals('A', $itemMap[$itemA->id]['class']);
    }

    public function test_csv_export_generators_produce_valid_rfc4180_content(): void
    {
        /** @var WarehouseAnalyticsService $service */
        $service = app(WarehouseAnalyticsService::class);

        $agingCsv = $service->generateAgingCsv();
        $this->assertStringContainsString('Lot Number', $agingCsv);
        $this->assertStringContainsString('Aging Category', $agingCsv);

        $iraCsv = $service->generateIraCsv();
        $this->assertStringContainsString('Audit Ref', $iraCsv);
        $this->assertStringContainsString('Accurate Match', $iraCsv);
    }

    public function test_api_analytics_summary_endpoint(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/analytics/summary');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'aging' => [
                    'total_stock_kg',
                    'fresh_kg',
                    'normal_kg',
                    'warning_kg',
                    'critical_kg',
                    'expired_kg',
                    'critical_percentage',
                ],
                'ira' => [
                    'accuracy_percentage',
                    'audited_items_count',
                    'exact_matches_count',
                    'net_variance_kg',
                    'absolute_variance_kg',
                ],
                'utilization' => [
                    'total_bins',
                    'occupied_bins',
                    'occupancy_rate',
                    'blocked_bins',
                ],
            ]);
    }

    public function test_category_stock_breakdown_aggregates_active_inventory(): void
    {
        $rawItem = Item::where('category', Item::CATEGORY_RAW_RESIN)->firstOrFail();
        $fgItem = Item::where('category', Item::CATEGORY_FINISHED_GOOD)->firstOrFail();
        $bin = WarehouseBin::firstOrFail();

        $lotRaw = MaterialLot::create([
            'item_id' => $rawItem->id,
            'lot_number' => 'LOT-CAT-RAW-01',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 250.0,
            'remaining_quantity' => 250.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'manufactured_date' => now()->subDays(10),
            'expiry_date' => now()->addYear(),
        ]);
        InventoryBalance::create([
            'bin_id' => $bin->id,
            'item_id' => $rawItem->id,
            'material_lot_id' => $lotRaw->id,
            'quantity_on_hand' => 250.0,
            'quantity_reserved' => 0.0,
        ]);

        $lotFg = MaterialLot::create([
            'item_id' => $fgItem->id,
            'lot_number' => 'LOT-CAT-FG-01',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'initial_quantity' => 120.0,
            'remaining_quantity' => 120.0,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'manufactured_date' => now()->subDays(10),
            'expiry_date' => now()->addYear(),
        ]);
        InventoryBalance::create([
            'bin_id' => $bin->id,
            'item_id' => $fgItem->id,
            'material_lot_id' => $lotFg->id,
            'quantity_on_hand' => 120.0,
            'quantity_reserved' => 0.0,
        ]);

        /** @var WarehouseAnalyticsService $service */
        $service = app(WarehouseAnalyticsService::class);
        $breakdown = $service->getCategoryStockBreakdown();

        $map = collect($breakdown)->keyBy('category');

        $this->assertEquals(250.0, $map[Item::CATEGORY_RAW_RESIN]['total_kg']);
        $this->assertEquals(120.0, $map[Item::CATEGORY_FINISHED_GOOD]['total_kg']);
        $this->assertEquals(0.0, $map[Item::CATEGORY_MASTERBATCH]['total_kg']);
        $this->assertEquals(0.0, $map[Item::CATEGORY_PACKAGING]['total_kg']);
    }

    public function test_filament_executive_analytics_page_renders_successfully(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Warehouse Manager');

        $response = $this->actingAs($user)->get('/admin/executive-analytics');

        $response->assertOk();
    }
}
