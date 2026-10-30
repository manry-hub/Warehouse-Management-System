<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class StagingRequest extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_ALLOCATED = 'ALLOCATED';

    public const STATUS_PICKED = 'PICKED';

    public const STATUS_DISPATCHED_TO_MES = 'DISPATCHED_TO_MES';

    public const STATUS_COMPLETED = 'COMPLETED';

    protected $fillable = [
        'request_number',
        'mes_production_order_id',
        'target_machine_code',
        'status',
        'requested_at',
        'dispatched_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'dispatched_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StagingRequestItem::class);
    }

    public function pickingWaveOrders(): MorphMany
    {
        return $this->morphMany(PickingWaveOrder::class, 'orderable');
    }
}
