<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PickingWaveOrder extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_BATCHED = 'BATCHED';

    public const STATUS_PICKING = 'PICKING';

    public const STATUS_COMPLETED = 'COMPLETED';

    protected $fillable = [
        'picking_wave_id',
        'orderable_type',
        'orderable_id',
        'assigned_tote',
        'status',
    ];

    public function pickingWave(): BelongsTo
    {
        return $this->belongsTo(PickingWave::class, 'picking_wave_id');
    }

    public function orderable(): MorphTo
    {
        return $this->morphTo();
    }

    public function batchItems(): HasMany
    {
        return $this->hasMany(PickingBatchItem::class, 'picking_wave_order_id');
    }
}
