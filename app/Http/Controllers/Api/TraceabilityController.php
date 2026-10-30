<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LotGenealogyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TraceabilityController extends Controller
{
    public function __construct(
        protected LotGenealogyService $genealogyService
    ) {}

    public function backward(string $identifier): JsonResponse
    {
        try {
            $data = $this->genealogyService->traceBackward($identifier);

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function forward(string $lotNumber): JsonResponse
    {
        try {
            $data = $this->genealogyService->traceForward($lotNumber);

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function quarantine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lotNumber' => 'required|string',
            'reason' => 'required|string|max:255',
            'severity' => 'nullable|string|in:CRITICAL,MAJOR,MINOR',
        ]);

        try {
            $quarantine = $this->genealogyService->quarantineImpactedStock(
                identifier: $validated['lotNumber'],
                reason: $validated['reason'],
                userId: $request->user()?->id,
                severity: $validated['severity'] ?? 'MAJOR'
            );

            return response()->json([
                'success' => true,
                'message' => "Quarantine {$quarantine->quarantine_code} executed. {$quarantine->impacted_lots_count} lot(s) isolated.",
                'data' => [
                    'quarantineCode' => $quarantine->quarantine_code,
                    'reason' => $quarantine->reason,
                    'severity' => $quarantine->severity,
                    'impactedLotsCount' => $quarantine->impacted_lots_count,
                    'impactedTotalQuantity' => (float) $quarantine->impacted_total_quantity,
                    'status' => $quarantine->status,
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
