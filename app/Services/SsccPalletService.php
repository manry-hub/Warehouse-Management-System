<?php

namespace App\Services;

use App\Models\Item;
use App\Models\PalletContainer;
use InvalidArgumentException;

class SsccPalletService
{
    /**
     * Default GS1 Company Prefix for the manufacturing plant (7 digits).
     */
    protected string $companyPrefix = '8991234';

    /**
     * Calculate GS1 Modulo-10 Check Digit for a 17-digit string.
     *
     * GS1 standard algorithm:
     * - From right to left (position 1 to 17), multiply odd positions by 3 and even positions by 1.
     * - Sum all products.
     * - Check digit is (10 - (sum % 10)) % 10.
     */
    public function calculateCheckDigit(string $digits17): int
    {
        if (strlen($digits17) !== 17 || ! ctype_digit($digits17)) {
            throw new InvalidArgumentException("SSCC payload must be exactly 17 digits. Given: '{$digits17}'");
        }

        $sum = 0;
        $reversed = strrev($digits17);

        for ($i = 0; $i < 17; $i++) {
            $digit = (int) $reversed[$i];
            // 0-indexed: position 0 is odd in 1-based, multiply by 3; position 1 is even, multiply by 1
            $multiplier = ($i % 2 === 0) ? 3 : 1;
            $sum += $digit * $multiplier;
        }

        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Validate an 18-digit SSCC code against its GS1 Modulo-10 checksum.
     */
    public function validateSscc(string $sscc18): bool
    {
        $clean = preg_replace('/[^0-9]/', '', $sscc18);
        if (strlen($clean) !== 18) {
            return false;
        }

        $body = substr($clean, 0, 17);
        $expectedCheck = $this->calculateCheckDigit($body);
        $actualCheck = (int) $clean[17];

        return $expectedCheck === $actualCheck;
    }

    /**
     * Generate a new, unique 18-digit SSCC code.
     *
     * Format: Extension (1) + Company Prefix (7) + Serial (9) + Check Digit (1)
     */
    public function generateSscc(int $extensionDigit = 0): string
    {
        do {
            $serial = sprintf('%09d', random_int(1, 999999999));
            $digits17 = sprintf('%d%s%s', $extensionDigit, $this->companyPrefix, $serial);
            $checkDigit = $this->calculateCheckDigit($digits17);
            $sscc = $digits17.$checkDigit;
        } while (PalletContainer::where('sscc_code', $sscc)->exists());

        return $sscc;
    }

    /**
     * Create and containerize a new shipping pallet.
     */
    public function createPallet(
        string $itemId,
        int $cartonCount,
        float $grossWeightKg,
        ?string $deliveryOrderId = null,
        ?string $materialLotId = null,
        float $tareWeightKg = 15.0
    ): PalletContainer {
        $item = Item::findOrFail($itemId);

        if ($cartonCount <= 0) {
            throw new InvalidArgumentException('Carton count must be at least 1.');
        }

        $netWeightKg = max(0, $grossWeightKg - $tareWeightKg);
        $ssccCode = $this->generateSscc();

        return PalletContainer::create([
            'sscc_code' => $ssccCode,
            'delivery_order_id' => $deliveryOrderId,
            'item_id' => $item->id,
            'material_lot_id' => $materialLotId,
            'carton_count' => $cartonCount,
            'gross_weight_kg' => $grossWeightKg,
            'tare_weight_kg' => $tareWeightKg,
            'net_weight_kg' => $netWeightKg,
            'status' => PalletContainer::STATUS_SEALED,
        ]);
    }
}
