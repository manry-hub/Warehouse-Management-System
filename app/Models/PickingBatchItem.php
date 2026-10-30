<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickingBatchItem extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_PICKED = 'PICKED';

    public const STATUS_SHORT = 'SHORT';

    protected $fillable = [
        'picking_batch_id',
        'picking_wave_order_id',
        'item_id',
        'material_lot_id',
        'warehouse_bin_id',
        'quantity_to_pick',
        'quantity_picked',
        'target_tote',
        'pick_sequence',
        'status',
        'picked_at',
        'picked_by',
    ];

    protected $casts = [
        'quantity_to_pick' => 'decimal:4',
        'quantity_picked' => 'decimal:4',
        'pick_sequence' => 'integer',
        'picked_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PickingBatch::class, 'picking_batch_id');
    }

    public function waveOrder(): BelongsTo
    {
        return $this->belongsTo(PickingWaveOrder::class, 'picking_wave_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'warehouse_bin_id');
    }

    public function pickedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_by');
    }
}
