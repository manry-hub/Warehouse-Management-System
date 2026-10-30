<?php

namespace App\Jobs;

use App\Models\IntegrationOutbox;
use App\Services\MesGatewayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOutboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public ?int $outboxId = null
    ) {}

    public function handle(?MesGatewayService $gatewayService = null): void
    {
        $gatewayService ??= app(MesGatewayService::class);

        $query = IntegrationOutbox::where('status', IntegrationOutbox::STATUS_PENDING);
        if ($this->outboxId) {
            $query->where('id', $this->outboxId);
        }

        $pendingEvents = $query->limit(50)->get();

        foreach ($pendingEvents as $event) {
            $event->status = IntegrationOutbox::STATUS_PROCESSING;
            $event->attempts = $event->attempts + 1;
            $event->save();

            try {
                if ($event->event_type === 'materials.lot_dispatched') {
                    $gatewayService->registerMaterialLot($event->payload);

                    $event->status = IntegrationOutbox::STATUS_PROCESSED;
                    $event->processed_at = now();
                    $event->save();
                    Log::info("MES Outbox dispatched event [{$event->event_uuid}] successfully.");
                } else {
                    Log::warning("Unhandled outbox event type: [{$event->event_type}]");
                    $event->status = IntegrationOutbox::STATUS_PROCESSED;
                    $event->processed_at = now();
                    $event->save();
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
