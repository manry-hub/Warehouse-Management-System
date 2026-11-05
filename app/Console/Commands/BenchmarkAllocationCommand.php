<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Services\FifoAllocationService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Symfony\Component\Process\Process;

class BenchmarkAllocationCommand extends Command
{
    protected $signature = 'wms:benchmark-allocation
        {--workers=10 : Number of concurrent picker worker processes}
        {--requests=5 : Allocations per worker}
        {--sku=RAW-PP-INJ-503 : Material SKU to allocate from}
        {--quantity=10.0 : Quantity in KG per allocation request}
        {--release : Immediately release reservations after allocation to maintain steady pool}
        {--worker-id= : Internal flag to run in child worker mode}
        {--output-file= : Internal output path for child worker}';

    protected $description = 'Benchmark FIFO lot allocation performance and lock contention under concurrent load';

    public function handle(FifoAllocationService $service): int
    {
        $workerId = $this->option('worker-id');

        if ($workerId !== null) {
            return $this->runWorker((int) $workerId, $service);
        }

        return $this->runOrchestrator();
    }

    /**
     * Orchestrate parallel worker sub-processes and aggregate metrics.
     */
    protected function runOrchestrator(): int
    {
        $workers = (int) $this->option('workers');
        $requestsPerWorker = (int) $this->option('requests');
        $sku = (string) $this->option('sku');
        $quantity = (float) $this->option('quantity');
        $release = (bool) $this->option('release');

        $item = Item::where('sku', $sku)->first();
        if (! $item) {
            $this->error("Material SKU [{$sku}] not found.");

            return self::FAILURE;
        }

        $totalStock = InventoryBalance::where('item_id', $item->id)->sum('quantity_on_hand');
        $totalReserved = InventoryBalance::where('item_id', $item->id)->sum('quantity_reserved');
        $availableStock = $totalStock - $totalReserved;

        $this->info('========================================================================');
        $this->info('  WMS CONCURRENT ALLOCATION LOAD BENCHMARK (Oracle Database 21c XE)');
        $this->info('========================================================================');
        $this->line("Target SKU:              <comment>{$sku}</comment> ({$item->name})");
        $this->line('Total Balances in Pool:  <comment>'.InventoryBalance::where('item_id', $item->id)->count().'</comment>');
        $this->line("Available Stock:         <comment>{$availableStock} KG</comment>");
        $this->line("Concurrent Workers:      <comment>{$workers}</comment>");
        $this->line("Requests per Worker:     <comment>{$requestsPerWorker}</comment>");
        $this->line('Total Allocations:       <comment>'.($workers * $requestsPerWorker).'</comment>');
        $this->line("Quantity per Request:    <comment>{$quantity} KG</comment>");
        $this->line('Auto-Release:            <comment>'.($release ? 'YES' : 'NO').'</comment>');
        $this->newLine();

        $processes = [];
        $tempFiles = [];
        $startTime = microtime(true);

        for ($w = 1; $w <= $workers; $w++) {
            $outputFile = sys_get_temp_dir()."/wms_bench_worker_{$w}_".uniqid().'.json';
            $tempFiles[] = $outputFile;

            $cmd = [
                PHP_BINARY,
                base_path('artisan'),
                'wms:benchmark-allocation',
                "--worker-id={$w}",
                "--requests={$requestsPerWorker}",
                "--sku={$sku}",
                "--quantity={$quantity}",
                "--output-file={$outputFile}",
            ];

            if ($release) {
                $cmd[] = '--release';
            }

            $env = array_merge($_SERVER, [
                'ORACLE_HOME' => env('ORACLE_HOME', '/home/ughway/.oracle-client'),
                'LD_LIBRARY_PATH' => env('LD_LIBRARY_PATH', '/home/ughway/.oracle-client/lib'),
                'PHP_INI_SCAN_DIR' => env('PHP_INI_SCAN_DIR', ':/home/ughway/.oracle-client'),
            ]);

            $process = new Process($cmd, base_path(), $env);
            $process->start();
            $processes[$w] = $process;
        }

        $this->comment("Dispatched {$workers} parallel worker processes. Waiting for execution...");

        // Monitor processes to completion
        while (count($processes) > 0) {
            foreach ($processes as $w => $process) {
                if (! $process->isRunning()) {
                    if (! $process->isSuccessful()) {
                        $this->warn("Worker #{$w} exited with error: ".trim($process->getErrorOutput()));
                    }
                    unset($processes[$w]);
                }
            }
            usleep(20 * 1000);
        }

        $totalDuration = microtime(true) - $startTime;

        // Aggregate statistics
        $latencies = [];
        $successCount = 0;
        $insufficientCount = 0;
        $deadlockCount = 0;
        $errorCount = 0;
        $totalAllocatedKg = 0.0;

        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $data = json_decode($content, true) ?? [];
                unlink($file);

                foreach ($data['samples'] ?? [] as $sample) {
                    $latencies[] = $sample['latency_ms'];
                    if ($sample['status'] === 'SUCCESS') {
                        $successCount++;
                        $totalAllocatedKg += (float) ($sample['allocated_kg'] ?? 0.0);
                    } elseif ($sample['status'] === 'INSUFFICIENT_STOCK') {
                        $insufficientCount++;
                    } elseif ($sample['status'] === 'DEADLOCK') {
                        $deadlockCount++;
                    } else {
                        $errorCount++;
                    }
                }
            }
        }

        if (empty($latencies)) {
            $this->error('No benchmark samples collected. Workers may have failed to execute.');

            return self::FAILURE;
        }

        sort($latencies);
        $totalSamples = count($latencies);
        $throughput = $totalSamples / ($totalDuration ?: 0.001);

        $getPercentile = function (array $values, float $percentile): float {
            $count = count($values);
            if ($count === 0) {
                return 0.0;
            }
            $index = ($percentile / 100) * ($count - 1);
            $fraction = $index - floor($index);
            $low = $values[(int) floor($index)];
            $high = $values[(int) ceil($index)];

            return round($low + $fraction * ($high - $low), 2);
        };

        $this->newLine();
        $this->info('========================================================================');
        $this->info('  BENCHMARK RESULTS & LATENCY PROFILE');
        $this->info('========================================================================');

        $this->table(
            ['Metric', 'Result'],
            [
                ['Total Requests Processed', $totalSamples],
                ['Successful Allocations', "<info>{$successCount}</info> (".round(($successCount / $totalSamples) * 100, 1).'%)'],
                ['Insufficient Stock Results', "<comment>{$insufficientCount}</comment> (".round(($insufficientCount / $totalSamples) * 100, 1).'%)'],
                ['Deadlocks (ORA-00060)', $deadlockCount > 0 ? "<error>{$deadlockCount}</error>" : '<info>0</info>'],
                ['Other Errors / Timeouts', $errorCount > 0 ? "<error>{$errorCount}</error>" : '<info>0</info>'],
                ['Total Volume Allocated', round($totalAllocatedKg, 2).' KG'],
                ['Elapsed Wall-Clock Time', round($totalDuration, 3).' s'],
                ['Throughput', round($throughput, 2).' tx/sec'],
            ]
        );

        $this->table(
            ['Latency Percentile', 'Duration (ms)'],
            [
                ['Minimum (Fastest)', round(min($latencies), 2).' ms'],
                ['Average (Mean)', round(array_sum($latencies) / $totalSamples, 2).' ms'],
                ['Median (p50)', $getPercentile($latencies, 50).' ms'],
                ['90th Percentile (p90)', $getPercentile($latencies, 90).' ms'],
                ['95th Percentile (p95)', $getPercentile($latencies, 95).' ms'],
                ['99th Percentile (p99)', $getPercentile($latencies, 99).' ms'],
                ['Maximum (Slowest)', round(max($latencies), 2).' ms'],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Execute in child worker process: run requests and dump JSON metrics.
     */
    protected function runWorker(int $workerId, FifoAllocationService $service): int
    {
        $requests = (int) $this->option('requests');
        $sku = (string) $this->option('sku');
        $quantity = (float) $this->option('quantity');
        $release = (bool) $this->option('release');
        $outputFile = (string) $this->option('output-file');

        $item = Item::where('sku', $sku)->first();
        if (! $item) {
            return self::FAILURE;
        }

        $samples = [];

        for ($i = 1; $i <= $requests; $i++) {
            $t0 = hrtime(true);
            $status = 'UNKNOWN';
            $allocatedKg = 0.0;
            $allocations = [];

            try {
                $allocations = $service->allocate($item, $quantity);
                $status = 'SUCCESS';
                $allocatedKg = array_sum(array_column($allocations, 'quantity'));

                if ($release) {
                    foreach ($allocations as $alloc) {
                        $service->releaseReservation($alloc['balance_id'], $alloc['quantity']);
                    }
                }
            } catch (InsufficientStockException) {
                $status = 'INSUFFICIENT_STOCK';
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'ORA-00060')) {
                    $status = 'DEADLOCK';
                } else {
                    $status = 'QUERY_ERROR: '.$e->getCode();
                }
            } catch (\Throwable $e) {
                $status = 'ERROR: '.get_class($e);
            }

            $t1 = hrtime(true);
            $latencyMs = round(($t1 - $t0) / 1e6, 2);

            $samples[] = [
                'request_num' => $i,
                'latency_ms' => $latencyMs,
                'status' => $status,
                'allocated_kg' => $allocatedKg,
            ];
        }

        if ($outputFile) {
            file_put_contents($outputFile, json_encode([
                'worker_id' => $workerId,
                'samples' => $samples,
            ]));
        }

        return self::SUCCESS;
    }
}
