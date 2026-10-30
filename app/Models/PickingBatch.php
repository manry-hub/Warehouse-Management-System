<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickingBatch extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_ASSIGNED = 'ASSIGNED';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'batch_number',
        'picking_wave_id',
        'assigned_to',
        'cart_code',
        'status',
        'total_picks',
        'completed_picks',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_picks' => 'integer',
        'completed_picks' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function pickingWave(): BelongsTo
    {
        return $this->belongsTo(PickingWave::class, 'picking_wave_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PickingBatchItem::class, 'picking_batch_id')->orderBy('pick_sequence');
    }
}
