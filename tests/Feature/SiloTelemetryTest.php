<?php

namespace Tests\Feature;

use App\Filament\Resources\PurchaseRequisitionResource;
use App\Filament\Resources\SiloResource;
use App\Filament\Resources\SiloResource\Pages\ViewSilo;
use App\Models\Item;
use App\Models\PurchaseRequisition;
use App\Models\Silo;
use App\Models\SiloTelemetryReading;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\WarehouseZone;
use App\Services\SiloGeometryService;
use App\Services\SiloTelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SiloTelemetryTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Warehouse $warehouse;

    private WarehouseZone $zone;

    private WarehouseBin $bin;

    private Item $item;

    private Silo $silo;

    private SiloGeometryService $geometryService;

    private SiloTelemetryService $telemetryService;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'Warehouse Manager', 'guard_name' => 'web']);
        $this->manager = User::factory()->create([
            'email' => 'manager.silo@wms.local',
        ]);
        $this->manager->assignRole($role);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-TEST',
            'name' => 'Test Silo Plant',
            'is_active' => true,
        ]);

        $this->zone = WarehouseZone::create([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'ZONE-SILO-TEST',
            'name' => 'Test Silo Farm',
            'zone_type' => WarehouseZone::TYPE_SILO,
            'is_temperature_controlled' => false,
        ]);

        $this->bin = WarehouseBin::create([
            'zone_id' => $this->zone->id,
            'bin_code' => 'SILO-TEST-BIN-01',
            'aisle' => 'SILO',
            'rack' => 'BAY-01',
            'shelf' => '1',
            'bin' => '1',
            'max_weight_kg' => 60000.00,
            'max_volume_cbm' => 120.00,
        ]);

        $this->item = Item::create([
            'sku' => 'TEST-PP-BULK',
            'name' => 'Polypropylene Homopolymer Test',
            'category' => Item::CATEGORY_RAW_RESIN,
            'unit_of_measure' => 'KG',
            'standard_pack_qty' => 1000.00,
            'minimum_safety_stock' => 10000.00,
            'shelf_life_days' => 365,
            'allow_regrind' => true,
            'max_regrind_percentage' => 20.00,
            'is_active' => true,
        ]);

        $this->silo = Silo::create([
            'warehouse_id' => $this->warehouse->id,
            'zone_id' => $this->zone->id,
            'bin_id' => $this->bin->id,
            'item_id' => $this->item->id,
            'code' => 'SILO-TEST-01',
            'name' => 'Test Silo 1',
            'total_height_m' => 12.00,
            'diameter_m' => 3.50,
            'cone_height_m' => 2.00,
            'sensor_deadband_m' => 0.50,
            'bulk_density_kg_m3' => 520.00,
            'max_capacity_kg' => 50000.00,
            'reorder_threshold_kg' => 12000.00,
            'critical_threshold_kg' => 5000.00,
            'current_distance_m' => 6.00,
            'current_weight_kg' => 23000.00,
            'current_fill_percentage' => 46.00,
            'status' => Silo::STATUS_NORMAL,
            'is_active' => true,
        ]);

        $this->geometryService = app(SiloGeometryService::class);
        $this->telemetryService = app(SiloTelemetryService::class);
    }

    public function test_geometry_service_calculates_cylindrical_and_conical_volume_accurately(): void
    {
        // Total Height = 12m, Cone Height = 2m, Diameter = 3.5m (r = 1.75m), Bulk Density = 520 kg/m3

        // Case 1: Material level h = 1.0m (inside conical bottom hopper, h <= 2.0m)
        // V = (1/3) * pi * r^2 * (h^3 / H_cone^2) = (1/3) * pi * (1.75)^2 * (1 / 4) = 0.80164 m3
        $distance = 11.0; // h = 12.0 - 11.0 = 1.0m
        $metrics = $this->geometryService->calculateMetrics($this->silo, $distance);

        $this->assertEquals(1.0, $metrics['material_height_m']);
        $this->assertEquals(0.802, $metrics['volume_m3']);
        $expectedWeight = round(((1.0 / 3.0) * M_PI * (1.75 ** 2) * (1.0 / 4.0)) * 520.00, 2);
        $this->assertEquals($expectedWeight, $metrics['weight_kg']);
        $this->assertEquals(Silo::STATUS_CRITICAL, $metrics['status']);

        // Case 2: Material level h = 6.0m (fills cone 2m + 4m in cylinder)
        // V_cone = (1/3) * pi * (1.75)^2 * 2 = 6.414 m3
        // V_cyl = pi * (1.75)^2 * 4 = 38.4845 m3
        // Total V = 44.898 m3
        $distance2 = 6.0; // h = 12.0 - 6.0 = 6.0m
        $metrics2 = $this->geometryService->calculateMetrics($this->silo, $distance2);

        $this->assertEquals(6.0, $metrics2['material_height_m']);
        $this->assertEquals(44.899, $metrics2['volume_m3']);
        $expectedWeight2 = round((((1.0 / 3.0) * M_PI * (1.75 ** 2) * 2.0) + (M_PI * (1.75 ** 2) * 4.0)) * 520.00, 2);
        $this->assertEquals($expectedWeight2, $metrics2['weight_kg']);
        $this->assertEquals(Silo::STATUS_NORMAL, $metrics2['status']);
    }

    public function test_geometry_service_handles_zero_cone_height_cleanly(): void
    {
        $flatSilo = Silo::create([
            'warehouse_id' => $this->warehouse->id,
            'zone_id' => $this->zone->id,
            'bin_id' => $this->bin->id,
            'item_id' => $this->item->id,
            'code' => 'SILO-FLAT-01',
            'name' => 'Flat Silo',
            'total_height_m' => 10.00,
            'diameter_m' => 4.00,
            'cone_height_m' => 0.00, // Flat bottom
            'sensor_deadband_m' => 0.00,
            'bulk_density_kg_m3' => 500.00,
            'max_capacity_kg' => 60000.00,
            'reorder_threshold_kg' => 10000.00,
            'critical_threshold_kg' => 4000.00,
        ]);

        // h = 5.0m, r = 2.0m -> V = pi * 4 * 5 = 62.83185 m3
        $metrics = $this->geometryService->calculateMetrics($flatSilo, 5.0);
        $this->assertEquals(5.0, $metrics['material_height_m']);
        $this->assertEquals(62.832, $metrics['volume_m3']);
        $expectedFlatWeight = round((M_PI * (2.0 ** 2) * 5.0) * 500.00, 2);
        $this->assertEquals($expectedFlatWeight, $metrics['weight_kg']);
    }

    public function test_telemetry_service_ingests_reading_and_updates_silo_state(): void
    {
        $distance = 5.0; // h = 7.0m -> ~28,294 kg
        $result = $this->telemetryService->ingestReading(
            silo: $this->silo,
            distanceMeters: $distance,
            sensorType: SiloTelemetryReading::SENSOR_RADAR_80GHZ,
            temperatureC: 27.5,
            batteryPercentage: 97.0
        );

        $this->assertDatabaseHas('silo_telemetry_readings', [
            'silo_id' => $this->silo->id,
            'distance_m' => 5.0,
            'sensor_type' => SiloTelemetryReading::SENSOR_RADAR_80GHZ,
        ]);

        $this->silo->refresh();
        $this->assertEquals(5.0, (float) $this->silo->current_distance_m);
        $this->assertEquals($result['metrics']['weight_kg'], (float) $this->silo->current_weight_kg);
        $this->assertEquals(Silo::STATUS_NORMAL, $this->silo->status);
        $this->assertFalse($result['reorder_triggered']);
        $this->assertNull($result['purchase_requisition']);
    }

    public function test_telemetry_service_triggers_draft_purchase_requisition_when_below_threshold(): void
    {
        // Distance 10.5m -> material height 1.5m -> mass ~1,407 kg (well below reorder threshold 12,000 kg)
        $result = $this->telemetryService->ingestReading(
            silo: $this->silo,
            distanceMeters: 10.5,
            sensorType: SiloTelemetryReading::SENSOR_RADAR_80GHZ
        );

        $this->assertTrue($result['reorder_triggered']);
        $this->assertNotNull($result['purchase_requisition']);

        $pr = $result['purchase_requisition'];
        $this->assertEquals($this->silo->id, $pr->silo_id);
        $this->assertEquals($this->item->id, $pr->item_id);
        $this->assertEquals(PurchaseRequisition::STATUS_DRAFT, $pr->status);
        $this->assertEquals(PurchaseRequisition::REQUESTER_SYSTEM, $pr->requested_by_type);
        $this->assertGreaterThanOrEqual(20000.0, (float) $pr->requested_quantity_kg);

        $this->silo->refresh();
        $this->assertEquals(Silo::STATUS_CRITICAL, $this->silo->status);
    }

    public function test_telemetry_service_enforces_idempotency_and_prevents_duplicate_purchase_requisitions(): void
    {
        // First low reading triggers PR
        $result1 = $this->telemetryService->ingestReading(
            silo: $this->silo,
            distanceMeters: 10.5,
            sensorType: SiloTelemetryReading::SENSOR_RADAR_80GHZ
        );
        $this->assertTrue($result1['reorder_triggered']);
        $this->assertNotNull($result1['purchase_requisition']);

        // Second low reading shortly after
        $result2 = $this->telemetryService->ingestReading(
            silo: $this->silo,
            distanceMeters: 10.6,
            sensorType: SiloTelemetryReading::SENSOR_RADAR_80GHZ
        );
        $this->assertFalse($result2['reorder_triggered']);
        $this->assertNull($result2['purchase_requisition']);

        // Total count of requisitions for this silo must strictly remain 1
        $this->assertEquals(1, PurchaseRequisition::where('silo_id', $this->silo->id)->count());
    }

    public function test_api_endpoint_rejects_unauthorized_request(): void
    {
        $response = $this->postJson('/api/v1/telemetry/silo-reading', [
            'silo_code' => $this->silo->code,
            'distance_m' => 4.5,
        ]);

        $response->assertStatus(401);
    }

    public function test_api_endpoint_ingests_reading_with_valid_api_key(): void
    {
        config(['services.silo.telemetry_key' => 'secret-test-key-123']);

        $response = $this->withHeaders([
            'X-Telemetry-Key' => 'secret-test-key-123',
        ])->postJson('/api/v1/telemetry/silo-reading', [
            'silo_code' => $this->silo->code,
            'distance_m' => 5.2,
            'sensor_type' => 'RADAR_80GHZ',
            'temperature_c' => 28.1,
            'battery_percentage' => 99.0,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Telemetry reading ingested successfully',
                'data' => [
                    'silo_code' => $this->silo->code,
                    'status' => 'NORMAL',
                ],
            ]);

        $this->assertDatabaseHas('silo_telemetry_readings', [
            'silo_id' => $this->silo->id,
            'distance_m' => 5.2,
            'temperature_c' => 28.1,
        ]);
    }

    public function test_filament_silo_resource_and_view_page_render_successfully(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(SiloResource\Pages\ListSilos::class)
            ->assertSuccessful()
            ->assertSee($this->silo->code);

        Livewire::test(ViewSilo::class, ['record' => $this->silo->id])
            ->assertSuccessful()
            ->assertSee($this->silo->code)
            ->assertSee('Vertical Vessel Gauge')
            ->assertSee('Recent Telemetry Readings');
    }

    public function test_filament_purchase_requisition_resource_renders_and_supports_approval(): void
    {
        $this->actingAs($this->manager);

        $pr = PurchaseRequisition::create([
            'pr_number' => 'PR-TEST-APPROVAL-01',
            'silo_id' => $this->silo->id,
            'item_id' => $this->item->id,
            'requested_by_type' => PurchaseRequisition::REQUESTER_SYSTEM,
            'requested_quantity_kg' => 25000.00,
            'status' => PurchaseRequisition::STATUS_DRAFT,
        ]);

        Livewire::test(PurchaseRequisitionResource\Pages\ListPurchaseRequisitions::class)
            ->assertSuccessful()
            ->assertSee('PR-TEST-APPROVAL-01')
            ->callTableAction('approve', $pr);

        $pr->refresh();
        $this->assertEquals(PurchaseRequisition::STATUS_APPROVED, $pr->status);
        $this->assertNotNull($pr->approved_at);
    }
}
