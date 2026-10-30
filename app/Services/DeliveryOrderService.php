<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class DeliveryOrderService
{
    public function __construct(
        protected InventoryMovementService $movementService
    ) {}

    /**
     * Generate Delivery Order PDF document.
     */
    public function generatePdf(DeliveryOrder $order): string
    {
        $order->load(['items.item', 'items.lot']);
        $pdf = Pdf::loadView('pdf.delivery-order', ['order' => $order]);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Scan-to-verify carton loading on outbound truck.
     */
    public function scanCarton(DeliveryOrder $order, string $cartonBarcode): array
    {
        return DB::transaction(function () use ($order, $cartonBarcode) {
            $item = $order->items()
                ->where('carton_barcode', $cartonBarcode)
                ->first();

            if (! $item) {
                // If not assigned by barcode, check if there is an unfulfilled matching item
                $item = $order->items()
                    ->where('is_verified_scanned', false)
                    ->first();
            }

            if (! $item) {
                return [
                    'success' => false,
                    'message' => "Scanned barcode [{$cartonBarcode}] does not belong to pending items in Delivery Order {$order->do_number}",
                ];
            }

            $item->is_verified_scanned = true;
            $item->quantity_loaded = $item->quantity_ordered;
            $item->carton_barcode = $cartonBarcode;
            $item->save();

            // Check if all items in the order are loaded
            $remaining = $order->items()->where('is_verified_scanned', false)->count();
            if ($remaining === 0) {
                $order->status = DeliveryOrder::STATUS_LOADED;
                $order->total_cartons = $order->items()->count();
                $order->save();
            } else {
                $order->status = DeliveryOrder::STATUS_PICKING;
                $order->save();
            }

            return [
                'success' => true,
                'message' => "Carton [{$cartonBarcode}] verified and loaded.",
                'remaining_cartons' => $remaining,
                'order_status' => $order->status,
            ];
        });
    }

    /**
     * Finalize dispatch and record SHIPMENT ledger entries.
     */
    public function dispatch(DeliveryOrder $order, User $user): DeliveryOrder
    {
        return DB::transaction(function () use ($order, $user) {
            if ($order->status === DeliveryOrder::STATUS_DISPATCHED) {
                return $order;
            }

            $order->load(['items.lot', 'items.sourceBin']);

            foreach ($order->items as $item) {
                if ($item->lot && $item->sourceBin) {
                    $this->movementService->recordShipment(
                        lot: $item->lot,
                        sourceBin: $item->sourceBin,
                        quantity: (float) $item->quantity_loaded,
                        doNumber: $order->do_number,
                        user: $user
                    );
                }
            }

            $order->status = DeliveryOrder::STATUS_DISPATCHED;
            $order->dispatched_at = now();
            $order->save();

            return $order;
        });
    }
}
