<?php

namespace App\Jobs;

use App\Models\IntegrationOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessOutboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public ?int $outboxId = null
    ) {}

    public function handle(): void
    {
        $query = IntegrationOutbox::where('status', IntegrationOutbox::STATUS_PENDING);
        if ($this->outboxId) {
            $query->where('id', $this->outboxId);
        }

        $pendingEvents = $query->limit(50)->get();

        $mesBaseUrl = config('services.mes.base_url', env('MES_BASE_URL', 'http://127.0.0.1:3000'));
        $mesToken = config('services.mes.integration_token', env('MES_INTEGRATION_TOKEN', 'test-integration-token'));

        foreach ($pendingEvents as $event) {
            $event->status = IntegrationOutbox::STATUS_PROCESSING;
            $event->attempts = $event->attempts + 1;
            $event->save();

            try {
                if ($event->event_type === 'materials.lot_dispatched') {
                    $response = Http::withToken($mesToken)
                        ->timeout(5)
                        ->post("{$mesBaseUrl}/api/v1/materials/lots", $event->payload);

                    if ($response->successful()) {
                        $event->status = IntegrationOutbox::STATUS_PROCESSED;
                        $event->processed_at = now();
                        $event->save();
                        Log::info("MES Outbox dispatched event [{$event->event_uuid}] successfully.");
                    } else {
                        throw new \RuntimeException("MES API returned status {$response->status()}: ".$response->body());
                    }
                }
            } catch (\Throwable $e) {
                $event->last_error = $e->getMessage();
                $event->status = ($event->attempts >= $this->tries)
                    ? IntegrationOutbox::STATUS_FAILED
                    : IntegrationOutbox::STATUS_PENDING;
                $event->save();

                Log::error("Failed to dispatch MES Outbox event [{$event->event_uuid}]: {$e->getMessage()}");
            }
        }
    }
}
