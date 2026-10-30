<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickingWave extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_DELIVERY_ORDERS = 'DELIVERY_ORDERS';

    public const TYPE_STAGING_DEMANDS = 'STAGING_DEMANDS';

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_RELEASED = 'RELEASED';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const PRIORITY_URGENT = 'URGENT';

    public const PRIORITY_NORMAL = 'NORMAL';

    public const PRIORITY_LOW = 'LOW';

    protected $fillable = [
        'wave_number',
        'wave_type',
        'status',
        'priority',
        'cutoff_time',
        'assigned_to',
        'total_orders',
        'total_lines',
        'picked_lines',
        'notes',
        'released_at',
        'completed_at',
    ];

    protected $casts = [
        'cutoff_time' => 'datetime',
        'released_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_orders' => 'integer',
        'total_lines' => 'integer',
        'picked_lines' => 'integer',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function waveOrders(): HasMany
    {
        return $this->hasMany(PickingWaveOrder::class, 'picking_wave_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(PickingBatch::class, 'picking_wave_id');
    }
}
