<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\MaterialLot;
use Database\Seeders\ItemCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WarehouseTopologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MesWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected string $secret = 'test-mes-secret-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mes.webhook_secret' => $this->secret]);
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(WarehouseTopologySeeder::class);
        $this->seed(ItemCatalogSeeder::class);
    }

    public function test_rejects_webhook_with_missing_or_invalid_signature(): void
    {
        $payload = [
            'eventId' => 'e98e4d27-4a0b-46cb-84e1-2cfbca04a601',
            'eventType' => 'packaging.unit_created',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'cartonBarcode' => 'CARTON-20261030-0042',
                'orderNumber' => 'PO-AUTO-7721',
                'partNumber' => 'PART-BZL-FR-LH',
                'quantityPacked' => 50,
            ],
        ];

        // 1. Missing header -> 401
        $response1 = $this->postJson('/api/v1/webhooks/mes', $payload);
        $response1->assertStatus(401);

        // 2. Invalid HMAC -> 401
        $response2 = $this->withHeaders([
            'X-MES-Signature' => 'invalid-hmac-sha256-hash',
        ])->postJson('/api/v1/webhooks/mes', $payload);
        $response2->assertStatus(401);
    }

    public function test_processes_valid_packaging_webhook_and_enforces_idempotency(): void
    {
        $eventId = 'e98e4d27-4a0b-46cb-84e1-2cfbca04a601';
        $cartonBarcode = 'CARTON-20261030-0042';

        $payload = [
            'eventId' => $eventId,
            'eventType' => 'packaging.unit_created',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'cartonBarcode' => $cartonBarcode,
                'orderNumber' => 'PO-AUTO-7721',
                'partNumber' => 'PART-BZL-FR-LH',
                'quantityPacked' => 50,
                'unitOfMeasure' => 'PCS',
                'machineId' => 'IMM-03',
                'moldCode' => 'MOLD-BZL-01',
            ],
        ];

        $rawJson = json_encode($payload);
        $signature = hash_hmac('sha256', $rawJson, $this->secret);

        // First delivery: should process and return 200
        $response = $this->call('POST', '/api/v1/webhooks/mes', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MES_SIGNATURE' => $signature,
        ], $rawJson);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'eventId' => $eventId,
                    'cartonBarcode' => $cartonBarcode,
                    'quantity' => 50.0,
                ],
            ]);

        // Verify movement recorded
        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'FG_INTAKE',
            'reference_type' => 'MES_PACKAGING_EVENT',
            'reference_id' => $eventId,
            'quantity' => 50.0,
        ]);

        // Second duplicate delivery with same eventId: must return 200 without creating second balance
        $repeatResponse = $this->call('POST', '/api/v1/webhooks/mes', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MES_SIGNATURE' => $signature,
        ], $rawJson);

        $repeatResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'idempotent' => true,
            ]);

        // Assert exactly one movement exists
        $movementCount = InventoryMovement::where('reference_id', $eventId)->count();
        $this->assertEquals(1, $movementCount);

        // Assert lot balance is exactly 50
        $lot = MaterialLot::where('lot_number', $cartonBarcode)->firstOrFail();
        $totalStock = InventoryBalance::where('material_lot_id', $lot->id)->sum('quantity_on_hand');
        $this->assertEquals(50.0, (float) $totalStock);
    }
}
