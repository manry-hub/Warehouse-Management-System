<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MesGatewayService
{
    public function __construct(
        protected MesAuthService $authService
    ) {}

    /**
     * Resolve the MES Material UUID by material code, or automatically create it if missing.
     */
    public function resolveMaterialId(
        string $materialCode,
        string $materialName = '',
        string $category = 'BASE_RESIN',
        float $density = 0.905
    ): string {
        $baseUrl = rtrim((string) config('services.mes.base_url', 'http://127.0.0.1:3000'), '/');

        return $this->executeWithAuth(function (string $token) use ($baseUrl, $materialCode, $materialName, $category, $density) {
            // 1. Check existing materials list in MES
            $response = Http::withToken($token)
                ->timeout(5)
                ->acceptJson()
                ->get("{$baseUrl}/api/v1/materials");

            if ($response->successful()) {
                $body = $response->json();
                $materials = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;
                if (is_array($materials)) {
                    foreach ($materials as $mat) {
                        if (is_array($mat) && isset($mat['code']) && $mat['code'] === $materialCode) {
                            return (string) $mat['id'];
                        }
                    }
                }
            }

            // 2. Not found: create material definition in MES
            $createResponse = Http::withToken($token)
                ->timeout(5)
                ->acceptJson()
                ->post("{$baseUrl}/api/v1/materials", [
                    'code' => $materialCode,
                    'name' => $materialName ?: "Material {$materialCode}",
                    'category' => $category,
                    'densityGcm3' => $density,
                ]);

            $createdId = $createResponse->json('id') ?? $createResponse->json('data.id');
            if ($createResponse->successful() && $createdId) {
                Log::info("Auto-registered material [{$materialCode}] in MES catalog.");

                return (string) $createdId;
            }

            throw new RuntimeException("Failed to resolve or create MES material [{$materialCode}]: ".$createResponse->body());
        });
    }

    /**
     * Register a dispatched material lot in MES complying strictly with CreateMaterialLotDto.
     *
     * @param  array<string, mixed>  $lotPayload
     * @return array<string, mixed>
     */
    public function registerMaterialLot(array $lotPayload): array
    {
        $baseUrl = rtrim((string) config('services.mes.base_url', 'http://127.0.0.1:3000'), '/');

        // Resolve material UUID
        $materialCode = $lotPayload['materialCode'] ?? $lotPayload['material_code'] ?? 'RES-RAW';
        $materialName = $lotPayload['materialName'] ?? $lotPayload['material_name'] ?? '';
        $category = str_contains(strtoupper($materialCode), 'MB') ? 'MASTERBATCH' : 'BASE_RESIN';

        $materialId = $lotPayload['materialId'] ?? $this->resolveMaterialId($materialCode, $materialName, $category);

        // Format strictly to MES CreateMaterialLotDto (forbidNonWhitelisted: true)
        $dto = [
            'materialId' => $materialId,
            'lotNumber' => $lotPayload['lotNumber'] ?? $lotPayload['lot_number'],
            'initialQtyKg' => (float) ($lotPayload['quantityKg'] ?? $lotPayload['initialQtyKg'] ?? $lotPayload['quantity_kg'] ?? 0),
        ];

        $supplierLot = $lotPayload['supplierLot'] ?? $lotPayload['vendorHeatNumber'] ?? $lotPayload['vendor_heat_number'] ?? null;
        if (! empty($supplierLot)) {
            $dto['supplierLot'] = (string) $supplierLot;
        }

        $expiry = $lotPayload['expirationDate'] ?? $lotPayload['expiryDate'] ?? $lotPayload['expiry_date'] ?? null;
        if (! empty($expiry)) {
            $dto['expirationDate'] = Carbon::parse($expiry)->toISOString();
        }

        return $this->executeWithAuth(function (string $token) use ($baseUrl, $dto) {
            $response = Http::withToken($token)
                ->timeout(5)
                ->acceptJson()
                ->post("{$baseUrl}/api/v1/materials/lots", $dto);

            if ($response->successful()) {
                Log::info("MES successfully registered material lot [{$dto['lotNumber']}].");

                return (array) $response->json();
            }

            // Handle Idempotency: If lot already registered in MES, treat as success
            $errorBody = $response->body();
            if ($response->status() === 400 && str_contains($errorBody, 'already exists')) {
                Log::warning("Lot [{$dto['lotNumber']}] already exists in MES. Acknowledging as idempotent success.");

                return [
                    'idempotent' => true,
                    'message' => 'Lot already registered in MES',
                    'lotNumber' => $dto['lotNumber'],
                ];
            }

            throw new RuntimeException("MES returned status {$response->status()} on lot creation: {$errorBody}");
        });
    }

    /**
     * Execute a callback with automatic JWT retry on 401 Unauthorized.
     *
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    protected function executeWithAuth(callable $callback): mixed
    {
        $token = $this->authService->getAccessToken();

        try {
            return $callback($token);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), 'Unauthorized')) {
                Log::warning('MES token expired or rejected (401). Refreshing token and retrying...');
                $freshToken = $this->authService->getAccessToken(forceRefresh: true);

                return $callback($freshToken);
            }

            throw $e;
        }
    }
}
