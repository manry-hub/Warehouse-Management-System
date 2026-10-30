<?php

namespace App\Console\Commands;

use App\Services\ReplenishmentService;
use Illuminate\Console\Command;

class EvaluateReplenishmentsCommand extends Command
{
    protected $signature = 'wms:evaluate-replenishments {--zone= : Filter by specific warehouse zone UUID}';

    protected $description = 'Scan forward-pick face bins, calculate stock deficits against min thresholds, and trigger automated replenishment missions';

    public function handle(ReplenishmentService $service): int
    {
        $zone = $this->option('zone');
        $this->info('Evaluating forward-pick face bin deficits...');

        $spawnedTasks = $service->evaluateDeficits($zone);

        if ($spawnedTasks->isEmpty()) {
            $this->info('No forward-pick bin deficits detected. All pick faces are adequately stocked.');

            return self::SUCCESS;
        }

        $this->info("Generated {$spawnedTasks->count()} replenishment missions:");
        $tableData = $spawnedTasks->map(fn ($task) => [
            $task->task_number,
            $task->targetBin->bin_code ?? 'N/A',
            $task->sourceBin->bin_code ?? 'N/A',
            $task->item->item_code ?? 'N/A',
            $task->materialLot->lot_number ?? 'N/A',
            (float) $task->requested_quantity_kg.' KG',
            $task->priority,
        ]);

        $this->table(['Task #', 'Target Pick Bin', 'Source Bulk Bin', 'SKU', 'Lot #', 'Deficit Qty', 'Priority'], $tableData);

        return self::SUCCESS;
    }
}
