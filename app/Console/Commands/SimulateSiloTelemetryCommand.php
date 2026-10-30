<?php

namespace App\Console\Commands;

use App\Models\Silo;
use App\Models\SiloTelemetryReading;
use App\Services\SiloTelemetryService;
use Illuminate\Console\Command;

class SimulateSiloTelemetryCommand extends Command
{
    protected $signature = 'wms:simulate-silo-telemetry 
                            {silo? : Silo code, e.g. SILO-PP-01} 
                            {--distance= : Distance in meters from top sensor to polymer surface} 
                            {--temp=25.0 : Temperature in Celsius}';

    protected $description = 'Simulate or ingest a sensor telemetry distance reading for a bulk resin silo';

    public function handle(SiloTelemetryService $telemetryService): int
    {
        $siloCode = $this->argument('silo');

        if (! $siloCode) {
            $silo = Silo::first();
            if (! $silo) {
                $this->error('No silos found in database. Please seed or configure a silo first.');

                return self::FAILURE;
            }
        } else {
            $silo = Silo::where('code', $siloCode)->first();
            if (! $silo) {
                $this->error("Silo [{$siloCode}] not found.");

                return self::FAILURE;
            }
        }

        $distance = $this->option('distance') !== null
            ? (float) $this->option('distance')
            : (float) $silo->total_height_m * 0.45; // default ~55% full

        $temp = (float) $this->option('temp');

        $this->info("Simulating telemetry ingestion for Silo [{$silo->code} - {$silo->name}]...");
        $this->line("Measured Distance: {$distance} m | Total Silo Height: {$silo->total_height_m} m");

        $result = $telemetryService->ingestReading(
            silo: $silo,
            distanceMeters: $distance,
            sensorType: SiloTelemetryReading::SENSOR_RADAR_80GHZ,
            temperatureC: $temp,
            batteryPercentage: 98.5,
            rawPayload: ['simulated' => true, 'cli' => true]
        );

        $metrics = $result['metrics'];

        $this->table(
            ['Metric', 'Value'],
            [
                ['Silo Code', $silo->code],
                ['Material Height', $metrics['material_height_m'].' m'],
                ['Calculated Volume', $metrics['volume_m3'].' m³'],
                ['Stored Mass', number_format($metrics['weight_kg'], 2).' kg'],
                ['Fill Percentage', $metrics['fill_percentage'].'%'],
                ['Status', $metrics['status']],
                ['Reorder Triggered?', $result['reorder_triggered'] ? 'YES' : 'NO'],
                ['Requisition Number', $result['purchase_requisition']?->pr_number ?? 'None (Sufficient or Already Open)'],
            ]
        );

        return self::SUCCESS;
    }
}
