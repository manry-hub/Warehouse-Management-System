<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class GatePassService
{
    /**
     * Process security gate clearance, verifying truck plate and driver signature before issuing gate out pass.
     */
    public function clearGatePass(
        string $doNumber,
        string $sealNumber,
        string $driverSignature,
        ?string $verifiedPlate = null,
        ?string $verifiedDriver = null,
        ?int $securityOfficerId = null
    ): DeliveryOrder {
        if (empty(trim($sealNumber))) {
            throw new InvalidArgumentException('Truck security seal number is required for gate clearance.');
        }

        if (empty(trim($driverSignature))) {
            throw new InvalidArgumentException('Driver electronic signature is required on glass.');
        }

        return DB::transaction(function () use ($doNumber, $sealNumber, $driverSignature, $verifiedPlate, $verifiedDriver, $securityOfficerId) {
            $deliveryOrder = DeliveryOrder::where('do_number', trim($doNumber))
                ->lockForUpdate()
                ->firstOrFail();

            if ($deliveryOrder->status === DeliveryOrder::STATUS_DISPATCHED_GATE_OUT) {
                throw new RuntimeException("Delivery Order [{$deliveryOrder->do_number}] has already passed security gate clearance.");
            }

            if (! in_array($deliveryOrder->status, [DeliveryOrder::STATUS_LOADED, DeliveryOrder::STATUS_DISPATCHED])) {
                throw new RuntimeException("Delivery Order [{$deliveryOrder->do_number}] is not in a dispatchable state. Current status: {$deliveryOrder->status}");
            }

            $gatePassNumber = 'GP-'.date('Ymd').'-'.strtoupper(Str::random(4));

            $deliveryOrder->gate_pass_number = $gatePassNumber;
            $deliveryOrder->security_officer_id = $securityOfficerId;
            $deliveryOrder->seal_number = trim($sealNumber);
            $deliveryOrder->driver_signature = $driverSignature;
            $deliveryOrder->gate_out_at = now();
            $deliveryOrder->status = DeliveryOrder::STATUS_DISPATCHED_GATE_OUT;

            if ($verifiedPlate) {
                $deliveryOrder->vehicle_plate_number = trim($verifiedPlate);
            }
            if ($verifiedDriver) {
                $deliveryOrder->driver_name = trim($verifiedDriver);
            }

            if (! $deliveryOrder->dispatched_at) {
                $deliveryOrder->dispatched_at = now();
            }

            $deliveryOrder->save();

            Log::info("SECURITY GATE PASS ISSUED [{$gatePassNumber}]: Delivery Order [{$deliveryOrder->do_number}] cleared for gate departure. Seal: {$sealNumber}");

            return $deliveryOrder;
        });
    }
}
