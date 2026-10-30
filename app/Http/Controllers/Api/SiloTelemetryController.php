<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Silo;
use App\Models\SiloTelemetryReading;
use App\Services\SiloTelemetryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiloTelemetryController extends Controller
{
    public function __construct(
        protected SiloTelemetryService $telemetryService
    ) {}

    /**
     * Ingest a single silo level telemetry distance reading.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'silo_code' => ['required', 'string', 'exists:silos,code'],
            'distance_m' => ['required', 'numeric', 'min:0'],
            'sensor_type' => ['nullable', 'string'],
            'temperature_c' => ['nullable', 'numeric'],
            'battery_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $silo = Silo::with('item')->where('code', $validated['silo_code'])->firstOrFail();

        $recordedAt = ! empty($validated['recorded_at'])
            ? Carbon::parse($validated['recorded_at'])
            : now();

        $sensorType = $validated['sensor_type'] ?? SiloTelemetryReading::SENSOR_RADAR_80GHZ;
        $tempC = isset($validated['temperature_c']) ? (float) $validated['temperature_c'] : null;
        $battery = isset($validated['battery_percentage']) ? (float) $validated['battery_percentage'] : null;

        $result = $this->telemetryService->ingestReading(
            silo: $silo,
            distanceMeters: (float) $validated['distance_m'],
            sensorType: $sensorType,
            temperatureC: $tempC,
            batteryPercentage: $battery,
            rawPayload: $request->all(),
            recordedAt: $recordedAt
        );

        return response()->json([
            'success' => true,
            'message' => 'Telemetry reading ingested successfully',
            'data' => [
                'silo_code' => $silo->code,
                'silo_name' => $silo->name,
                'item_sku' => $silo->item->sku,
                'material_height_m' => $result['metrics']['material_height_m'],
                'volume_m3' => $result['metrics']['volume_m3'],
                'weight_kg' => $result['metrics']['weight_kg'],
                'fill_percentage' => $result['metrics']['fill_percentage'],
                'status' => $result['metrics']['status'],
                'reorder_triggered' => $result['reorder_triggered'],
                'purchase_requisition_number' => $result['purchase_requisition']?->pr_number,
                'recorded_at' => $result['reading']->recorded_at->toDateTimeString(),
            ],
        ], 200);
    }
}
