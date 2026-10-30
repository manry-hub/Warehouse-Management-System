<?php

namespace Tests\Feature;

use App\Models\IntegrationOutbox;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\WarehouseBin;
use App\Models\WarehouseZone;
use App\Services\MesAuthService;
use App\Services\MesGatewayService;
use Database\Seeders\ItemCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WarehouseTopologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MesLiveIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected string $mesBaseUrl = 'http://127.0.0.1:3000';

    protected string $webhookSecret = 'test-mes-secret-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mes.base_url' => $this->mesBaseUrl,
            'services.mes.username' => 'admin',
            'services.mes.password' => 'password123',
            'services.mes.webhook_secret' => $this->webhookSecret,
        ]);

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(WarehouseTopologySeeder::class);
        $this->seed(ItemCatalogSeeder::class);

        Cache::flush();
    }

    public function test_mes_auth_service_authenticates_and_caches_jwt_token(): void
    {
        Http::fake([
            "{$this->mesBaseUrl}/api/v1/auth/login" => Http::sequence()
                ->push(['accessToken' => 'jwt-token-alpha-12345'], 200)
                ->push(['accessToken' => 'jwt-token-beta-67890'], 200),
        ]);

        $authService = app(MesAuthService::class);

        // 1. Initial login
        $token1 = $authService->getAccessToken();
        $this->assertEquals('jwt-token-alpha-12345', $token1);
        $this->assertTrue(Cache::has(MesAuthService::CACHE_KEY));

        // 2. Cached retrieval (no new HTTP call)
        $tokenCached = $authService->getAccessToken();
        $this->assertEquals('jwt-token-alpha-12345', $tokenCached);

        // 3. Forced refresh
        $tokenRefreshed = $authService->getAccessToken(forceRefresh: true);
        $this->assertEquals('jwt-token-beta-67890', $tokenRefreshed);

        Http::assertSentCount(2);
    }

    public function test_mes_gateway_resolves_material_uuid_and_registers_whitelisted_lot_dto(): void
    {
        $materialsDatabase = [
            'RES-PP-HOMO' => 'mat-uuid-pp-homo',
        ];

        Http::fake([
            "{$this->mesBaseUrl}/api/v1/auth/login" => Http::response(['accessToken' => 'jwt-mes-active'], 200),
            "{$this->mesBaseUrl}/api/v1/materials/lots" => Http::response([
                'id' => 'lot-uuid-001',
                'lotNumber' => 'LOT-PP-20261030-88',
                'materialId' => 'mat-uuid-pp-homo',
                'initialQtyKg' => 500.0,
            ], 201),
            "{$this->mesBaseUrl}/api/v1/materials*" => function ($request) use (&$materialsDatabase) {
                if (str_contains($request->url(), '/materials/lots')) {
                    return Http::response([
                        'id' => 'lot-uuid-001',
                        'lotNumber' => 'LOT-PP-20261030-88',
                        'materialId' => 'mat-uuid-pp-homo',
                        'initialQtyKg' => 500.0,
                    ], 201);
                }

                if ($request->method() === 'GET') {
                    $list = [];
                    foreach ($materialsDatabase as $code => $id) {
                        $list[] = ['id' => $id, 'code' => $code, 'name' => "Resin {$code}"];
                    }

                    return Http::response($list, 200);
                }

                if ($request->method() === 'POST') {
                    $code = $request['code'];
                    $newId = 'mat-uuid-custom-resin';
                    $materialsDatabase[$code] = $newId;

                    return Http::response(['id' => $newId, 'code' => $code], 201);
                }

                return Http::response([], 200);
            },
        ]);

        $gateway = app(MesGatewayService::class);

        // A. Resolve existing material
        $id1 = $gateway->resolveMaterialId('RES-PP-HOMO');
        $this->assertEquals('mat-uuid-pp-homo', $id1);

        // B. Resolve missing material (auto-creates)
        $id2 = $gateway->resolveMaterialId('RES-CUSTOM-EXP', 'Custom Experimental Resin');
        $this->assertEquals('mat-uuid-custom-resin', $id2);

        // C. Register lot with strictly whitelisted fields
        $result = $gateway->registerMaterialLot([
            'materialCode' => 'RES-PP-HOMO',
            'lotNumber' => 'LOT-PP-20261030-88',
            'quantityKg' => 500.0,
            'vendorHeatNumber' => 'HEAT-PP-9901',
            'expiryDate' => '2027-10-30',
            // Non-whitelisted fields to ensure they are filtered out
            'warehouseLocation' => 'ZONE-RAW-BIN-01',
            'packageType' => 'OCTABIN',
        ]);

        $this->assertEquals('lot-uuid-001', $result['id']);

        Http::assertSent(function ($request) {
            if ($request->url() === "{$this->mesBaseUrl}/api/v1/materials/lots") {
                $data = $request->data();

                // Assert whitelisted DTO fields are present
                $this->assertEquals('mat-uuid-pp-homo', $data['materialId']);
                $this->assertEquals('LOT-PP-20261030-88', $data['lotNumber']);
                $this->assertEquals(500.0, $data['initialQtyKg']);
                $this->assertEquals('HEAT-PP-9901', $data['supplierLot']);
                $this->assertArrayHasKey('expirationDate', $data);

                // Assert non-whitelisted fields are NOT included
                $this->assertArrayNotHasKey('warehouseLocation', $data);
                $this->assertArrayNotHasKey('packageType', $data);
                $this->assertArrayNotHasKey('materialCode', $data);

                return true;
            }

            return true;
        });
    }

    public function test_sync_mes_outbox_artisan_command_processes_pending_lots(): void
    {
        Http::fake([
            "{$this->mesBaseUrl}/api/v1/auth/login" => Http::response(['accessToken' => 'jwt-token-outbox'], 200),
            "{$this->mesBaseUrl}/api/v1/materials" => Http::response([
                ['id' => 'mat-uuid-abs-hi', 'code' => 'RES-ABS-HI', 'name' => 'ABS High Impact Resin'],
            ], 200),
            "{$this->mesBaseUrl}/api/v1/materials/lots" => Http::response(['id' => 'mes-abs-lot-1'], 201),
        ]);

        $outbox1 = IntegrationOutbox::create([
            'event_uuid' => (string) Str::uuid(),
            'event_type' => 'materials.lot_dispatched',
            'payload' => [
                'lotNumber' => 'LOT-ABS-20261030-01',
                'materialCode' => 'RES-ABS-HI',
                'quantityKg' => 750.0,
            ],
            'status' => IntegrationOutbox::STATUS_PENDING,
        ]);

        $this->artisan('wms:sync-mes-outbox')
            ->expectsOutputToContain('Successfully processed 1 event(s)')
            ->assertExitCode(0);

        $outbox1->refresh();
        $this->assertEquals(IntegrationOutbox::STATUS_PROCESSED, $outbox1->status);
        $this->assertNotNull($outbox1->processed_at);
    }

    public function test_inbound_mes_packaging_webhook_verifies_signature_and_records_fg_intake_with_ledger_invariants(): void
    {
        $fgItem = Item::where('sku', 'PRD-AUTO-BUMPER')->first();
        $this->assertNotNull($fgItem, 'Harmonized finished good item PRD-AUTO-BUMPER must exist.');

        $eventId = 'MES-EVT-CARTON-1001';
        $cartonBarcode = 'CARTON-BUMPER-20261030-001';
        $orderNumber = 'WO-AUTO-2026-001';
        $quantityPacked = 25.0;

        $payload = [
            'eventId' => $eventId,
            'eventType' => 'packaging.unit_created',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'cartonBarcode' => $cartonBarcode,
                'orderNumber' => $orderNumber,
                'partNumber' => 'PRD-AUTO-BUMPER',
                'quantityPacked' => $quantityPacked,
                'unitOfMeasure' => 'PCS',
                'machineId' => 'IMM-AUTO-01',
                'moldCode' => 'MOLD-BUMPER-01',
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = hash_hmac('sha256', $rawBody, $this->webhookSecret);

        // 1. Post signed webhook to WMS intake endpoint
        $response = $this->call('POST', '/api/v1/webhooks/mes', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MES_SIGNATURE' => $signature,
            'HTTP_X_MES_EVENT_ID' => $eventId,
        ], $rawBody);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'eventId' => $eventId,
                    'cartonBarcode' => $cartonBarcode,
                    'quantity' => 25.0,
                ],
            ]);

        // 2. Verify MaterialLot was created with MES COA metrics
        $lot = MaterialLot::where('lot_number', $cartonBarcode)->first();
        $this->assertNotNull($lot);
        $this->assertEquals($fgItem->id, $lot->item_id);
        $this->assertEquals('WO-AUTO-2026-001', $lot->vendor_lot_number);
        $this->assertEquals('IMM-AUTO-01', $lot->coa_metrics['machineId']);
        $this->assertEquals('MOLD-BUMPER-01', $lot->coa_metrics['moldCode']);

        // 3. Verify Inventory Movement was recorded in Finished Goods zone
        $movement = InventoryMovement::where('reference_type', 'MES_PACKAGING_EVENT')
            ->where('reference_id', $eventId)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('FG_INTAKE', $movement->movement_type);
        $this->assertEquals(25.0, (float) $movement->quantity);
        $this->assertNotNull($movement->destination_bin_id);

        $toBin = WarehouseBin::with('zone')->find($movement->destination_bin_id);
        $this->assertEquals(WarehouseZone::TYPE_FINISHED_GOODS, $toBin->zone->zone_type);

        // 4. Verify Inventory Balance matches movement exactly
        $balance = InventoryBalance::where('material_lot_id', $lot->id)
            ->where('bin_id', $toBin->id)
            ->first();

        $this->assertNotNull($balance);
        $this->assertEquals(25.0, (float) $balance->quantity_on_hand);

        // 5. Zero-Sum Invariant Check for this transaction
        $totalLotMovements = InventoryMovement::where('material_lot_id', $lot->id)->sum('quantity');
        $totalLotBalances = InventoryBalance::where('material_lot_id', $lot->id)->sum('quantity_on_hand');
        $this->assertEquals((float) $totalLotMovements, (float) $totalLotBalances);

        // 6. Idempotent re-delivery test: sending duplicate webhook must NOT create duplicate balance
        $repeatResponse = $this->call('POST', '/api/v1/webhooks/mes', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MES_SIGNATURE' => $signature,
            'HTTP_X_MES_EVENT_ID' => $eventId,
        ], $rawBody);

        $repeatResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'idempotent' => true,
            ]);

        $movementCount = InventoryMovement::where('reference_id', $eventId)->count();
        $this->assertEquals(1, $movementCount, 'Duplicate webhook must not create a duplicate inventory movement.');

        $balanceAfterRepeat = InventoryBalance::where('material_lot_id', $lot->id)->sum('quantity_on_hand');
        $this->assertEquals(25.0, (float) $balanceAfterRepeat, 'Balance must remain unchanged after idempotent webhook replay.');
    }
}
