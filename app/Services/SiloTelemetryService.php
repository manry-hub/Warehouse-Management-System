<?php

namespace App\Services;

use App\Models\PurchaseRequisition;
use App\Models\Silo;
use App\Models\SiloTelemetryReading;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SiloTelemetryService
{
    public function __construct(
        protected SiloGeometryService $geometryService
    ) {}

    /**
     * Ingest a telemetry distance reading for a silo and evaluate thresholds.
     *
     * @return array{
     *     reading: SiloTelemetryReading,
     *     metrics: array{
     *         material_height_m: float,
     *         volume_m3: float,
     *         weight_kg: float,
     *         fill_percentage: float,
     *         status: string
     *     },
     *     reorder_triggered: bool,
     *     purchase_requisition: ?PurchaseRequisition
     * }
     */
    public function ingestReading(
        Silo $silo,
        float $distanceMeters,
        string $sensorType = SiloTelemetryReading::SENSOR_RADAR_80GHZ,
        ?float $temperatureC = null,
        ?float $batteryPercentage = null,
        ?array $rawPayload = null,
        ?\DateTimeInterface $recordedAt = null
    ): array {
        return DB::transaction(function () use ($silo, $distanceMeters, $sensorType, $temperatureC, $batteryPercentage, $rawPayload, $recordedAt) {
            $timestamp = $recordedAt ?? now();
            $metrics = $this->geometryService->calculateMetrics($silo, $distanceMeters);

            // 1. Record reading timeseries
            $reading = SiloTelemetryReading::create([
                'silo_id' => $silo->id,
                'sensor_type' => $sensorType,
                'distance_m' => $distanceMeters,
                'material_height_m' => $metrics['material_height_m'],
                'volume_m3' => $metrics['volume_m3'],
                'weight_kg' => $metrics['weight_kg'],
                'fill_percentage' => $metrics['fill_percentage'],
                'temperature_c' => $temperatureC,
                'battery_percentage' => $batteryPercentage,
                'raw_payload' => $rawPayload,
                'recorded_at' => $timestamp,
            ]);

            // 2. Atomically update current silo state
            $silo->update([
                'current_distance_m' => $distanceMeters,
                'current_weight_kg' => $metrics['weight_kg'],
                'current_fill_percentage' => $metrics['fill_percentage'],
                'status' => $metrics['status'],
                'last_telemetry_at' => $timestamp,
            ]);

            // 3. Evaluate reorder threshold and trigger auto-requisition if needed
            $requisition = null;
            $reorderTriggered = false;

            if ($metrics['weight_kg'] <= (float) $silo->reorder_threshold_kg) {
                $requisition = $this->checkAndTriggerReorder($silo, $metrics['weight_kg']);
                $reorderTriggered = ($requisition !== null);
            }

            return [
                'reading' => $reading,
                'metrics' => $metrics,
                'reorder_triggered' => $reorderTriggered,
                'purchase_requisition' => $requisition,
            ];
        });
    }

    /**
     * Check if a purchase requisition is needed and create a draft if none is open.
     */
    public function checkAndTriggerReorder(Silo $silo, float $currentWeightKg): ?PurchaseRequisition
    {
        // Enforce Idempotency: Check if an active requisition already exists
        $hasActiveRequisition = PurchaseRequisition::where('silo_id', $silo->id)
            ->where('item_id', $silo->item_id)
            ->whereIn('status', [
                PurchaseRequisition::STATUS_DRAFT,
                PurchaseRequisition::STATUS_SUBMITTED,
                PurchaseRequisition::STATUS_APPROVED,
                PurchaseRequisition::STATUS_ORDERED,
            ])
            ->exists();

        if ($hasActiveRequisition) {
            Log::info("Silo {$silo->code} level ({$currentWeightKg} kg) is below reorder threshold ({$silo->reorder_threshold_kg} kg), but an active purchase requisition is already open.");

            return null;
        }

        // Standard bulk tanker load is ~25,000 kg, or up to remaining capacity
        $deficiency = (float) $silo->max_capacity_kg - $currentWeightKg;
        $orderQuantity = max(20000.0, min(30000.0, round($deficiency, 2)));

        $prNumber = 'PR-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -4));

        $requisition = PurchaseRequisition::create([
            'pr_number' => $prNumber,
            'silo_id' => $silo->id,
            'item_id' => $silo->item_id,
            'requested_by_type' => PurchaseRequisition::REQUESTER_SYSTEM,
            'requested_quantity_kg' => $orderQuantity,
            'status' => PurchaseRequisition::STATUS_DRAFT,
            'notes' => "Automated bulk replenishment requisition generated for {$silo->code} ({$silo->name}). Current stored weight is {$currentWeightKg} kg, below reorder point of {$silo->reorder_threshold_kg} kg.",
        ]);

        Log::warning("Automated Purchase Requisition {$prNumber} generated for {$silo->code} (Item: {$silo->item->sku}, Quantity: {$orderQuantity} kg).");

        return $requisition;
    }
}
