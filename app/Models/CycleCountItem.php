<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleCountItem extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_COUNTED = 'COUNTED';

    public const STATUS_RECOUNT_REQUESTED = 'RECOUNT_REQUESTED';

    public const STATUS_APPROVED = 'APPROVED';

    protected $fillable = [
        'cycle_count_order_id',
        'bin_id',
        'item_id',
        'material_lot_id',
        'recorded_quantity',
        'counted_quantity',
        'variance_quantity',
        'status',
        'counted_at',
        'counter_notes',
    ];

    protected $casts = [
        'recorded_quantity' => 'decimal:3',
        'counted_quantity' => 'decimal:3',
        'variance_quantity' => 'decimal:3',
        'counted_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(CycleCountOrder::class, 'cycle_count_order_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'bin_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id');
    }

    public function hasVariance(): bool
    {
        return $this->variance_quantity !== null && abs((float) $this->variance_quantity) > 0.0001;
    }

    public function isSurplus(): bool
    {
        return $this->variance_quantity !== null && (float) $this->variance_quantity > 0.0001;
    }

    public function isShortage(): bool
    {
        return $this->variance_quantity !== null && (float) $this->variance_quantity < -0.0001;
    }

    public function isMatch(): bool
    {
        return $this->variance_quantity !== null && abs((float) $this->variance_quantity) <= 0.0001;
    }
}
