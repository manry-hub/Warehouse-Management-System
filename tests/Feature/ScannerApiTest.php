<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\StagingRequest;
use App\Models\User;
use App\Models\WarehouseBin;
use Database\Seeders\ItemCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WarehouseTopologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScannerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(WarehouseTopologySeeder::class);
        $this->seed(ItemCatalogSeeder::class);
    }

    public function test_scanner_auth_login(): void
    {
        $response = $this->postJson('/api/v1/scanner/auth/login', [
            'email' => 'handler@wms.local',
            'password' => 'password',
            'device_name' => 'Honeywell-EDA51',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'token',
                'user' => ['id', 'name', 'email', 'roles'],
            ]);
    }

    public function test_scanner_inbound_receive_creates_qa_hold_lot_and_label(): void
    {
        $user = User::where('email', 'handler@wms.local')->firstOrFail();
        Sanctum::actingAs($user, ['scanner:access']);

        $response = $this->postJson('/api/v1/scanner/inbound/receive', [
            'po_number' => 'PO-2026-8801',
            'sku' => 'RAW-PP-INJ-503',
            'vendor_code' => 'VEND-LOTTE',
            'vendor_lot_number' => 'LT-49021',
            'vendor_heat_number' => 'HEAT-9921',
            'quantity_kg' => 500.0,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => MaterialLot::STATUS_QA_HOLD,
                    'item_sku' => 'RAW-PP-INJ-503',
                    'quantity_kg' => 500.0,
                ],
            ]);

        $this->assertDatabaseHas('material_lots', [
            'vendor_lot_number' => 'LT-49021',
            'vendor_heat_number' => 'HEAT-9921',
            'status' => MaterialLot::STATUS_QA_HOLD,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'RECEIPT',
            'quantity' => 500.0,
            'reference_id' => 'PO-2026-8801',
        ]);
    }

    public function test_scanner_picking_enforces_available_status_guard(): void
    {
        $user = User::where('email', 'handler@wms.local')->firstOrFail();
        Sanctum::actingAs($user, ['scanner:access']);

        $item = Item::where('sku', 'RAW-PP-INJ-503')->firstOrFail();
        $sourceBin = WarehouseBin::where('bin_code', 'RM-A-01-11')->firstOrFail();
        $stagingBin = WarehouseBin::where('bin_code', 'STG-IMM-01')->firstOrFail();

        // Lot still on QA_HOLD
        $holdLot = MaterialLot::create([
            'lot_number' => 'LOT-UNAPPROVED-01',
            'item_id' => $item->id,
            'status' => MaterialLot::STATUS_QA_HOLD,
        ]);

        $stagingRequest = StagingRequest::create([
            'request_number' => 'STG-REQ-001',
            'mes_production_order_id' => 'MES-ORD-7711',
            'target_machine_code' => 'IMM-01',
            'status' => StagingRequest::STATUS_PENDING,
        ]);

        $response = $this->postJson('/api/v1/scanner/picking/scan', [
            'staging_request_id' => $stagingRequest->id,
            'lot_number' => $holdLot->lot_number,
            'source_bin_code' => $sourceBin->bin_code,
            'staging_bin_code' => $stagingBin->bin_code,
            'quantity_kg' => 25.0,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }
}
