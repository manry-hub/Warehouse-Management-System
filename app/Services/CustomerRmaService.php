<?php

namespace App\Services;

use App\Models\CustomerRma;
use App\Models\CustomerRmaItem;
use App\Models\User;
use App\Models\WarehouseBin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerRmaService
{
    public function __construct(
        protected InventoryMovementService $movementService
    ) {}

    /**
     * Create a formal Customer RMA record with line items.
     */
    public function createRma(array $data, array $items): CustomerRma
    {
        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'At least one return item line must be provided.']);
        }

        return DB::transaction(function () use ($data, $items) {
            $rmaNumber = sprintf(
                'RMA-%s-%03d',
                now()->format('Ymd'),
                CustomerRma::whereDate('created_at', today())->count() + 1
            );

            $rma = CustomerRma::create([
                'rma_number' => $rmaNumber,
                'customer_name' => $data['customer_name'],
                'delivery_order_id' => $data['delivery_order_id'] ?? null,
                'defect_reason' => $data['defect_reason'],
                'status' => CustomerRma::STATUS_PENDING_RECEIPT,
                'disposition' => CustomerRma::DISPOSITION_PENDING,
                'total_cartons' => count($items),
                'received_cartons' => 0,
            ]);

            foreach ($items as $itemData) {
                CustomerRmaItem::create([
                    'customer_rma_id' => $rma->id,
                    'item_id' => $itemData['item_id'],
                    'material_lot_id' => $itemData['material_lot_id'] ?? null,
                    'carton_barcode' => $itemData['carton_barcode'] ?? null,
                    'quantity_returned' => (float) $itemData['quantity'],
                    'quantity_received' => 0,
                    'disposition' => CustomerRma::DISPOSITION_PENDING,
                ]);
            }

            return $rma;
        });
    }

    /**
     * Check in an incoming returned carton at the dock and stage it into a quarantine bin.
     */
    public function receiveCarton(
        CustomerRma $rma,
        string $cartonBarcode,
        WarehouseBin $quarantineBin,
        float $quantity,
        ?User $user = null
    ): array {
        if (in_array($rma->status, [CustomerRma::STATUS_RESOLVED, CustomerRma::STATUS_CANCELLED])) {
            throw ValidationException::withMessages([
                'status' => "RMA {$rma->rma_number} is already closed.",
            ]);
        }

        return DB::transaction(function () use ($rma, $cartonBarcode, $quarantineBin, $quantity, $user) {
            // Find line item matching carton or first pending line
            $line = $rma->items()
                ->where(function ($q) use ($cartonBarcode) {
                    $q->where('carton_barcode', $cartonBarcode)
                        ->orWhereNull('carton_barcode');
                })
                ->whereColumn('quantity_received', '<', 'quantity_returned')
                ->first();

            if (! $line) {
                $line = $rma->items()->firstOrFail();
            }

            // Record double-entry movement into quarantine bin
            $movement = $this->movementService->recordCustomerReturn(
                item: $line->item,
                lot: $line->lot,
                quarantineBin: $quarantineBin,
                quantity: $quantity,
                rmaNumber: $rma->rma_number,
                user: $user,
                notes: "Carton {$cartonBarcode} received under RMA {$rma->rma_number}"
            );

            $line->quantity_received = (float) $line->quantity_received + $quantity;
            if (! $line->carton_barcode) {
                $line->carton_barcode = $cartonBarcode;
            }
            $line->save();

            $rma->received_cartons = $rma->items()->whereColumn('quantity_received', '>=', 'quantity_returned')->count();
            if ($rma->received_cartons >= $rma->total_cartons && $rma->total_cartons > 0) {
                $rma->status = CustomerRma::STATUS_RECEIVED;
            } else {
                $rma->status = CustomerRma::STATUS_PENDING_RECEIPT;
            }
            $rma->save();

            return [
                'status' => 'success',
                'message' => "Carton {$cartonBarcode} checked into quarantine bin {$quarantineBin->bin_code}",
                'rma_number' => $rma->rma_number,
                'received_cartons' => $rma->received_cartons,
                'total_cartons' => $rma->total_cartons,
                'movement_uuid' => $movement->transaction_uuid,
            ];
        });
    }

    /**
     * Execute formal QA inspection and disposition (RESTOCK, SCRAP_REGRIND, SCRAP_DISPOSAL).
     */
    public function processDisposition(
        CustomerRma $rma,
        string $disposition,
        WarehouseBin $quarantineBin,
        ?WarehouseBin $targetBin = null,
        ?User $inspector = null,
        ?string $notes = null
    ): CustomerRma {
        if ($rma->status === CustomerRma::STATUS_RESOLVED) {
            throw ValidationException::withMessages(['status' => 'RMA has already been resolved.']);
        }

        return DB::transaction(function () use ($rma, $disposition, $quarantineBin, $targetBin, $inspector, $notes) {
            foreach ($rma->items as $itemLine) {
                $qty = (float) $itemLine->quantity_received;
                if ($qty <= 0) {
                    continue;
                }

                $lot = $itemLine->lot;

                if ($disposition === CustomerRma::DISPOSITION_RESTOCK && $targetBin) {
                    // Transfer from quarantine to active warehouse bin
                    if ($lot) {
                        $this->movementService->recordPutaway(
                            lot: $lot,
                            sourceBin: $quarantineBin,
                            destinationBin: $targetBin,
                            quantity: $qty,
                            user: $inspector,
                            notes: "RMA {$rma->rma_number} restocked to {$targetBin->bin_code}"
                        );
                    }
                } elseif ($disposition === CustomerRma::DISPOSITION_SCRAP_REGRIND && $targetBin) {
                    // Transfer from quarantine to regrind granulator staging
                    if ($lot) {
                        $this->movementService->recordPutaway(
                            lot: $lot,
                            sourceBin: $quarantineBin,
                            destinationBin: $targetBin,
                            quantity: $qty,
                            user: $inspector,
                            notes: "RMA {$rma->rma_number} routed to granulator regrind"
                        );
                    }
                } elseif ($disposition === CustomerRma::DISPOSITION_SCRAP_DISPOSAL) {
                    // Write off inventory permanently
                    if ($lot) {
                        $this->movementService->recordScrap(
                            lot: $lot,
                            sourceBin: $quarantineBin,
                            quantity: $qty,
                            referenceNumber: $rma->rma_number,
                            user: $inspector,
                            notes: "RMA {$rma->rma_number} scrap disposal: {$notes}"
                        );
                    }
                }

                $itemLine->disposition = $disposition;
                $itemLine->save();
            }

            $rma->disposition = $disposition;
            $rma->status = CustomerRma::STATUS_RESOLVED;
            $rma->qc_inspection_notes = $notes;
            $rma->inspected_by = $inspector?->id;
            $rma->inspected_at = now();
            $rma->credit_memo_number = sprintf('CM-%s-%03d', now()->format('Ymd'), CustomerRma::whereNotNull('credit_memo_number')->count() + 1);
            $rma->save();

            return $rma;
        });
    }
}
