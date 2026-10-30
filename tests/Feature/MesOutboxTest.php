<?php

namespace Tests\Feature;

use App\Jobs\ProcessOutboxJob;
use App\Models\IntegrationOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MesOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_outbox_job_dispatches_lot_to_mes(): void
    {
        Http::fake([
            'http://127.0.0.1:3000/api/v1/auth/login' => Http::response([
                'success' => true,
                'data' => [
                    'accessToken' => 'fake-jwt-token-xyz',
                ],
            ], 200),
            'http://127.0.0.1:3000/api/v1/materials*' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'id' => 'mat-uuid-1234',
                        'code' => 'RAW-PP-INJ-503',
                        'name' => 'Polypropylene Resin',
                    ],
                ],
            ], 200),
            'http://127.0.0.1:3000/api/v1/materials/lots' => Http::response([
                'success' => true,
                'data' => [
                    'id' => 'mes-lot-uuid-001',
                    'lotNumber' => 'LOT-PP-20261030-01',
                    'status' => 'AVAILABLE',
                ],
            ], 201),
        ]);

        $outbox = IntegrationOutbox::create([
            'event_uuid' => (string) Str::uuid(),
            'event_type' => 'materials.lot_dispatched',
            'payload' => [
                'lotNumber' => 'LOT-PP-20261030-01',
                'materialCode' => 'RAW-PP-INJ-503',
                'quantityKg' => 250.0,
            ],
            'status' => IntegrationOutbox::STATUS_PENDING,
        ]);

        (new ProcessOutboxJob($outbox->id))->handle();

        $outbox->refresh();
        $this->assertEquals(IntegrationOutbox::STATUS_PROCESSED, $outbox->status);
        $this->assertNotNull($outbox->processed_at);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:3000/api/v1/materials/lots'
                && $request['lotNumber'] === 'LOT-PP-20261030-01'
                && (float) $request['initialQtyKg'] === 250.0
                && $request['materialId'] === 'mat-uuid-1234';
        });
    }
}
