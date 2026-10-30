<?php

namespace App\Services;

use App\Exceptions\InvalidLotStateException;
use App\Models\MaterialLot;
use App\Models\User;

class LotQualityService
{
    /**
     * Release a lot from QA Hold to Available after inspection.
     */
    public function releaseToAvailable(
        MaterialLot $lot,
        User $inspector,
        ?array $coaMetrics = null
    ): MaterialLot {
        if ($lot->status !== MaterialLot::STATUS_QA_HOLD) {
            throw new InvalidLotStateException("Cannot release lot from state [{$lot->status}]. Only QA_HOLD lots can be released.");
        }

        if ($lot->expiry_date && $lot->expiry_date->isPast()) {
            throw new InvalidLotStateException("Cannot release lot [{$lot->lot_number}] because its expiration date has passed.");
        }

        $lot->status = MaterialLot::STATUS_AVAILABLE;
        $lot->qc_inspected_at = now();
        $lot->qc_inspected_by = $inspector->id;

        if ($coaMetrics !== null) {
            $lot->coa_metrics = array_merge($lot->coa_metrics ?? [], $coaMetrics);
        }

        $lot->save();

        return $lot;
    }

    /**
     * Place a lot into Quarantine cage state.
     */
    public function quarantine(
        MaterialLot $lot,
        User $inspector,
        string $reason
    ): MaterialLot {
        $lot->status = MaterialLot::STATUS_QUARANTINE;
        $lot->qc_inspected_at = now();
        $lot->qc_inspected_by = $inspector->id;

        $metrics = $lot->coa_metrics ?? [];
        $metrics['quarantine_reason'] = $reason;
        $metrics['quarantined_at'] = now()->toISOString();
        $lot->coa_metrics = $metrics;

        $lot->save();

        return $lot;
    }
}
