<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOutboxJob;
use App\Models\IntegrationOutbox;
use App\Services\MesGatewayService;
use Illuminate\Console\Command;

class SyncMesOutboxCommand extends Command
{
    protected $signature = 'wms:sync-mes-outbox {--limit=50 : Maximum number of events to process}';

    protected $description = 'Process and dispatch pending integration outbox events to the companion NestJS MES';

    public function handle(MesGatewayService $gatewayService): int
    {
        $limit = (int) $this->option('limit');
        $pendingCount = IntegrationOutbox::where('status', IntegrationOutbox::STATUS_PENDING)->count();

        if ($pendingCount === 0) {
            $this->info('No pending integration outbox events found. All events are up to date.');

            return self::SUCCESS;
        }

        $this->info("Found {$pendingCount} pending event(s). Processing up to {$limit} event(s)...");

        $job = new ProcessOutboxJob;
        $job->handle($gatewayService);

        $remainingCount = IntegrationOutbox::where('status', IntegrationOutbox::STATUS_PENDING)->count();
        $processedCount = $pendingCount - $remainingCount;

        $this->info("Successfully processed {$processedCount} event(s). Remaining pending: {$remainingCount}.");

        return self::SUCCESS;
    }
}
