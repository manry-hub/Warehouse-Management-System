<?php

namespace Tests\Feature;

use App\Filament\Pages\WarehouseHeatmapPage;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\WarehouseZone;
use App\Services\WarehouseHeatmapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseHeatmapTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Warehouse $warehouse;

    private WarehouseZone $zone;

    private WarehouseBin $binNormal;

    private WarehouseBin $binCritical;

    private WarehouseBin $binEmpty;

    private WarehouseBin $binBlocked;

    private Item $itemVirgin;

    private Item $itemRegrind;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup role & user
        $role = Role::firstOrCreate(['name' => 'Warehouse Manager', 'guard_name' => 'web']);
        $this->manager = User::factory()->create([
            'email' => 'manager.heatmap@wms.local',
        ]);
        $this->manager->assignRole($role);

        // Setup warehouse & zone
        $this->warehouse = Warehouse::create([
            'code' => 'WH-TEST',
            'name' => 'Test Industrial Facility',
            'address' => 'Industrial Area 5',
            'is_active' => true,
        ]);

        $this->zone = WarehouseZone::create([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'ZONE-TEST-RM',
            'name' => 'Raw Material Testing Zone',
            'zone_type' => WarehouseZone::TYPE_RAW_MATERIAL,
            'is_temperature_controlled' => false,
        ]);

        // Setup items
        $this->itemVirgin = Item::create([
            'sku' => 'PP-HOMO-HEATMAP',
            'name' => 'Polypropylene Homopolymer Heatmap Grade',
            'category' => Item::CATEGORY_RAW_RESIN,
            'unit_of_measure' => 'KG',
            'standard_pack_qty' => 25.0,
            'minimum_safety_stock' => 500.0,
            'shelf_life_days' => 730,
            'allow_regrind' => true,
            'max_regrind_percentage' => 20.0,
            'is_active' => true,
        ]);

        $this->itemRegrind = Item::create([
            'sku' => 'REGRIND-PP-HEATMAP',
            'name' => 'Recycled PP Granulated Heatmap Flakes',
            'category' => Item::CATEGORY_RAW_RESIN,
            'unit_of_measure' => 'KG',
            'standard_pack_qty' => 25.0,
            'minimum_safety_stock' => 200.0,
            'shelf_life_days' => 180,
            'allow_regrind' => false,
            'max_regrind_percentage' => 0.0,
            'is_active' => true,
        ]);

        // Setup Bins
        // Bin 1: Optimal (250 / 1500 = 16.7%)
        $this->binNormal = WarehouseBin::create([
            'zone_id' => $this->zone->id,
            'bin_code' => 'TEST-A-01-11',
            'aisle' => 'A',
            'rack' => '01',
            'shelf' => '1',
            'bin' => '1',
            'max_weight_kg' => 1500.0,
            'max_volume_cbm' => 2.5,
            'is_blocked' => false,
        ]);

        // Bin 2: Critical (1400 / 1500 = 93.3%)
        $this->binCritical = WarehouseBin::create([
            'zone_id' => $this->zone->id,
            'bin_code' => 'TEST-A-01-21',
            'aisle' => 'A',
            'rack' => '01',
            'shelf' => '2',
            'bin' => '1',
            'max_weight_kg' => 1500.0,
            'max_volume_cbm' => 2.5,
            'is_blocked' => false,
        ]);

        // Bin 3: Empty (0 / 1500 = 0%)
        $this->binEmpty = WarehouseBin::create([
            'zone_id' => $this->zone->id,
            'bin_code' => 'TEST-A-01-12',
            'aisle' => 'A',
            'rack' => '01',
            'shelf' => '1',
            'bin' => '2',
            'max_weight_kg' => 1500.0,
            'max_volume_cbm' => 2.5,
            'is_blocked' => false,
        ]);

        // Bin 4: Blocked (0 / 1000, is_blocked = true)
        $this->binBlocked = WarehouseBin::create([
            'zone_id' => $this->zone->id,
            'bin_code' => 'TEST-A-01-22',
            'aisle' => 'A',
            'rack' => '01',
            'shelf' => '2',
            'bin' => '2',
            'max_weight_kg' => 1000.0,
            'max_volume_cbm' => 2.0,
            'is_blocked' => true,
        ]);

        // Lots & Balances
        $lot1 = MaterialLot::create([
            'lot_number' => 'LOT-HM-001',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'item_id' => $this->itemVirgin->id,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'expiry_date' => now()->addDays(300),
        ]);

        InventoryBalance::create([
            'bin_id' => $this->binNormal->id,
            'item_id' => $this->itemVirgin->id,
            'material_lot_id' => $lot1->id,
            'quantity_on_hand' => 250.0,
            'quantity_reserved' => 50.0,
        ]);

        $lot2 = MaterialLot::create([
            'lot_number' => 'LOT-HM-002',
            'lot_type' => MaterialLot::TYPE_REGRIND,
            'item_id' => $this->itemRegrind->id,
            'regrind_generation' => 1,
            'regrind_source_machine' => 'IMM-03',
            'status' => MaterialLot::STATUS_AVAILABLE,
            'expiry_date' => now()->addDays(90),
        ]);

        InventoryBalance::create([
            'bin_id' => $this->binCritical->id,
            'item_id' => $this->itemRegrind->id,
            'material_lot_id' => $lot2->id,
            'quantity_on_hand' => 1400.0,
            'quantity_reserved' => 0.0,
        ]);
    }

    public function test_service_calculates_zone_metrics_accurately(): void
    {
        $service = app(WarehouseHeatmapService::class);
        $metrics = $service->getZoneMetrics($this->zone);

        $this->assertEquals(4, $metrics['total_bins']);
        $this->assertEquals(2, $metrics['occupied_bins']);
        $this->assertEquals(2, $metrics['empty_bins']);
        $this->assertEquals(1, $metrics['blocked_bins']);

        // 250 + 1400 = 1650 kg stored
        $this->assertEquals(1650.0, $metrics['total_stored_weight_kg']);

        // 1500 * 3 + 1000 = 5500 kg max capacity
        $this->assertEquals(5500.0, $metrics['total_max_weight_kg']);

        // 5500 - 1650 = 3850 kg headroom
        $this->assertEquals(3850.0, $metrics['available_headroom_kg']);

        // (1650 / 5500) * 100 = 30.0%
        $this->assertEquals(30.0, $metrics['weight_utilization_pct']);
        $this->assertEquals(50.0, $metrics['occupancy_rate_pct']);
    }

    public function test_service_evaluates_bin_fill_and_status(): void
    {
        $service = app(WarehouseHeatmapService::class);

        $evalNormal = $service->evaluateBin($this->binNormal, $this->zone);
        $this->assertEquals(WarehouseHeatmapService::STATUS_OPTIMAL, $evalNormal['status']);
        $this->assertEquals(16.7, $evalNormal['fill_percentage']);
        $this->assertEquals(1250.0, $evalNormal['headroom_kg']);

        $evalCritical = $service->evaluateBin($this->binCritical, $this->zone);
        $this->assertEquals(WarehouseHeatmapService::STATUS_CRITICAL, $evalCritical['status']);
        $this->assertEquals(93.3, $evalCritical['fill_percentage']);

        $evalEmpty = $service->evaluateBin($this->binEmpty, $this->zone);
        $this->assertEquals(WarehouseHeatmapService::STATUS_EMPTY, $evalEmpty['status']);
        $this->assertEquals(0.0, $evalEmpty['fill_percentage']);

        $evalBlocked = $service->evaluateBin($this->binBlocked, $this->zone);
        $this->assertEquals(WarehouseHeatmapService::STATUS_BLOCKED, $evalBlocked['status']);
        $this->assertTrue($evalBlocked['is_blocked']);
    }

    public function test_service_produces_hierarchical_topology_and_respects_filters(): void
    {
        $service = app(WarehouseHeatmapService::class);

        // Natural topology
        $topology = $service->getZoneTopology($this->zone);
        $this->assertArrayHasKey('A', $topology);
        $this->assertArrayHasKey('01', $topology['A']['racks']);

        // Shelf tier 2 should be ordered before shelf tier 1 (vertical top-to-bottom layout)
        $shelves = array_keys($topology['A']['racks']['01']['shelves']);
        $this->assertEquals(['2', '1'], $shelves);

        // Filter by SKU: searching for regrind should only return the critical bin
        $filteredBySku = $service->getZoneTopology($this->zone, ['sku' => 'REGRIND-PP']);
        $binsInSkuFilter = $filteredBySku['A']['racks']['01']['shelves']['2']['bins'];
        $this->assertCount(1, $binsInSkuFilter);
        $this->assertEquals('TEST-A-01-21', $binsInSkuFilter[0]['bin_code']);

        // Filter by Status: BLOCKED
        $filteredBlocked = $service->getZoneTopology($this->zone, ['status' => 'BLOCKED']);
        $this->assertCount(1, $filteredBlocked['A']['racks']['01']['shelves']['2']['bins']);
        $this->assertEquals('TEST-A-01-22', $filteredBlocked['A']['racks']['01']['shelves']['2']['bins'][0]['bin_code']);

        // Filter by Status: CRITICAL
        $filteredCritical = $service->getZoneTopology($this->zone, ['status' => 'CRITICAL']);
        $this->assertCount(1, $filteredCritical['A']['racks']['01']['shelves']['2']['bins']);
        $this->assertEquals('TEST-A-01-21', $filteredCritical['A']['racks']['01']['shelves']['2']['bins'][0]['bin_code']);
    }

    public function test_service_provides_detailed_inspection_and_toggles_blocked(): void
    {
        $service = app(WarehouseHeatmapService::class);

        $details = $service->getBinDetails($this->binCritical->id);
        $this->assertNotNull($details);
        $this->assertEquals('TEST-A-01-21', $details['bin_code']);
        $this->assertEquals(WarehouseHeatmapService::STATUS_CRITICAL, $details['status']);
        $this->assertCount(1, $details['balances']);
        $this->assertEquals('LOT-HM-002', $details['balances'][0]['lot_number']);
        $this->assertEquals('REGRIND', $details['balances'][0]['lot_type']);
        $this->assertEquals(1, $details['balances'][0]['regrind_generation']);
        $this->assertEquals('IMM-03', $details['balances'][0]['regrind_machine']);

        // Toggle blocked status
        $this->assertFalse($this->binNormal->fresh()->is_blocked);
        $service->toggleBinBlocked($this->binNormal->id);
        $this->assertTrue($this->binNormal->fresh()->is_blocked);

        $service->toggleBinBlocked($this->binNormal->id);
        $this->assertFalse($this->binNormal->fresh()->is_blocked);
    }

    public function test_service_calculates_warehouse_level_metrics_and_topology(): void
    {
        /** @var WarehouseHeatmapService $service */
        $service = app(WarehouseHeatmapService::class);

        $metrics = $service->getWarehouseMetrics($this->warehouse);
        $this->assertEquals(4, $metrics['total_bins']);
        $this->assertEquals(2, $metrics['occupied_bins']);
        $this->assertEquals(1, $metrics['blocked_bins']);

        $topology = $service->getWarehouseTopology($this->warehouse);
        $this->assertCount(1, $topology);
        $this->assertEquals('ZONE-TEST-RM', $topology[0]['zone_code']);
    }

    public function test_filament_heatmap_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->manager)
            ->get('/admin/visual-heatmap');

        $response->assertStatus(200);
        $response->assertSee('Visual Bin Topology & Heatmap');
        $response->assertSee('WH-TEST');
        $response->assertSee('ZONE-TEST-RM');
        $response->assertSee('TEST-A-01-11');
        $response->assertSee('TEST-A-01-21');
    }

    public function test_livewire_interactive_heatmap_component(): void
    {
        Livewire::actingAs($this->manager)
            ->test(WarehouseHeatmapPage::class)
            ->assertSet('selectedWarehouseId', $this->warehouse->id)
            ->assertSet('selectedZoneId', $this->zone->id)
            ->assertSee('TEST-A-01-11')
            ->call('selectBin', $this->binCritical->id)
            ->assertSet('selectedBinId', $this->binCritical->id)
            ->assertSee('LOT-HM-002')
            ->assertSee('IMM-03')
            ->call('toggleBlocked', $this->binCritical->id)
            ->assertNotified()
            ->call('closeDrawer')
            ->assertSet('selectedBinId', null)
            ->call('setViewMode', 'elevation')
            ->assertSet('viewMode', 'elevation')
            ->assertSee('TEST-A-01-11')
            ->call('setViewMode', 'matrix')
            ->assertSet('viewMode', 'matrix')
            ->assertSee('TEST-A-01-11')
            ->set('filterStatus', 'BLOCKED')
            ->assertSee('TEST-A-01-22')
            ->assertDontSee('TEST-A-01-11');
    }
}
