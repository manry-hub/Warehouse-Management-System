<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\PickingWave;
use App\Models\PickingWaveOrder;
use App\Models\StagingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WavePickingService
{
    public function __construct(
        protected BatchPickingService $batchService,
        protected FefoAllocationService $fefoService,
        protected PickPathRoutingService $routingService
    ) {}

    /**
     * Create a new picking wave from a set of Delivery Orders.
     */
    public function createWaveFromDeliveryOrders(array $deliveryOrderIds, array $attributes = []): PickingWave
    {
        if (empty($deliveryOrderIds)) {
            throw ValidationException::withMessages(['orders' => 'At least one delivery order must be selected.']);
        }

        return DB::transaction(function () use ($deliveryOrderIds, $attributes) {
            $waveNumber = sprintf('WAV-%s-%03d', now()->format('Ymd'), PickingWave::whereDate('created_at', today())->count() + 1);

            $wave = PickingWave::create([
                'wave_number' => $waveNumber,
                'wave_type' => PickingWave::TYPE_DELIVERY_ORDERS,
                'status' => PickingWave::STATUS_DRAFT,
                'priority' => $attributes['priority'] ?? PickingWave::PRIORITY_NORMAL,
                'cutoff_time' => $attributes['cutoff_time'] ?? null,
                'assigned_to' => $attributes['assigned_to'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'total_orders' => count($deliveryOrderIds),
                'total_lines' => 0,
                'picked_lines' => 0,
            ]);

            $totalLines = 0;
            $orders = DeliveryOrder::with('items')->whereIn('id', $deliveryOrderIds)->get();

            foreach ($orders as $order) {
                PickingWaveOrder::create([
                    'picking_wave_id' => $wave->id,
                    'orderable_type' => DeliveryOrder::class,
                    'orderable_id' => $order->id,
                    'status' => PickingWaveOrder::STATUS_PENDING,
                ]);

                $totalLines += $order->items->count();
            }

            $wave->total_lines = $totalLines;
            $wave->save();

            return $wave;
        });
    }

    /**
     * Create a new picking wave from MES Staging Requests.
     */
    public function createWaveFromStagingRequests(array $stagingRequestIds, array $attributes = []): PickingWave
    {
        if (empty($stagingRequestIds)) {
            throw ValidationException::withMessages(['requests' => 'At least one staging request must be selected.']);
        }

        return DB::transaction(function () use ($stagingRequestIds, $attributes) {
            $waveNumber = sprintf('WAV-STG-%s-%03d', now()->format('Ymd'), PickingWave::whereDate('created_at', today())->count() + 1);

            $wave = PickingWave::create([
                'wave_number' => $waveNumber,
                'wave_type' => PickingWave::TYPE_STAGING_DEMANDS,
                'status' => PickingWave::STATUS_DRAFT,
                'priority' => $attributes['priority'] ?? PickingWave::PRIORITY_NORMAL,
                'cutoff_time' => $attributes['cutoff_time'] ?? null,
                'assigned_to' => $attributes['assigned_to'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'total_orders' => count($stagingRequestIds),
                'total_lines' => 0,
                'picked_lines' => 0,
            ]);

            $totalLines = 0;
            $requests = StagingRequest::with('items')->whereIn('id', $stagingRequestIds)->get();

            foreach ($requests as $request) {
                PickingWaveOrder::create([
                    'picking_wave_id' => $wave->id,
                    'orderable_type' => StagingRequest::class,
                    'orderable_id' => $request->id,
                    'status' => PickingWaveOrder::STATUS_PENDING,
                ]);

                $totalLines += $request->items->count();
            }

            $wave->total_lines = $totalLines;
            $wave->save();

            return $wave;
        });
    }

    /**
     * Release wave: allocates unassigned lots via FEFO, partitions into multi-tote batches, and sets status to RELEASED.
     */
    public function releaseWave(PickingWave $wave, int $maxOrdersPerBatch = 4): PickingWave
    {
        if ($wave->status !== PickingWave::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => "Only DRAFT waves can be released. Current status: {$wave->status}",
            ]);
        }

        return DB::transaction(function () use ($wave, $maxOrdersPerBatch) {
            $waveOrders = $wave->waveOrders()->with('orderable.items')->get();

            // Validate and allocate FEFO lots for any order items lacking a lot or source bin
            foreach ($waveOrders as $waveOrder) {
                $order = $waveOrder->orderable;
                if (! $order) {
                    continue;
                }

                foreach ($order->items as $itemLine) {
                    if (! $itemLine->material_lot_id || ! $itemLine->source_bin_id) {
                        $qty = (float) ($itemLine->quantity_ordered ?? $itemLine->quantity_requested ?? 0);
                        $allocations = $this->fefoService->allocate($itemLine->item, $qty);

                        if (! empty($allocations)) {
                            $firstAlloc = $allocations[0];
                            $itemLine->material_lot_id = $firstAlloc['lot_id'];
                            $itemLine->source_bin_id = $firstAlloc['bin_id'];
                            $itemLine->save();
                        }
                    }
                }

                if ($order instanceof DeliveryOrder && $order->status === DeliveryOrder::STATUS_DRAFT) {
                    $order->status = DeliveryOrder::STATUS_PICKING;
                    $order->save();
                }
            }

            // Generate multi-tote batches with Serpentine S-path routing
            $batches = $this->batchService->generateBatchesForWave($wave, $maxOrdersPerBatch);

            $wave->status = PickingWave::STATUS_RELEASED;
            $wave->released_at = now();
            $wave->save();

            return $wave;
        });
    }

    /**
     * Cancel wave and reset order statuses if not yet started.
     */
    public function cancelWave(PickingWave $wave): PickingWave
    {
        if ($wave->status === PickingWave::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['status' => 'Completed waves cannot be cancelled.']);
        }

        return DB::transaction(function () use ($wave) {
            foreach ($wave->batches as $batch) {
                $batch->status = PickingBatch::STATUS_CANCELLED;
                $batch->save();
            }

            foreach ($wave->waveOrders as $waveOrder) {
                $waveOrder->status = PickingWaveOrder::STATUS_PENDING;
                $waveOrder->assigned_tote = null;
                $waveOrder->save();
            }

            $wave->status = PickingWave::STATUS_CANCELLED;
            $wave->save();

            return $wave;
        });
    }
}
