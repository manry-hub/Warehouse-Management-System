<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CycleCountOrder extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_COUNTED = 'COUNTED';

    public const STATUS_REVIEW = 'REVIEW';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_POSTED = 'POSTED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const TYPE_BLIND = 'BLIND';

    public const TYPE_ADHOC = 'ADHOC';

    public const TYPE_SCHEDULED = 'SCHEDULED';

    protected $fillable = [
        'count_number',
        'warehouse_id',
        'zone_id',
        'count_type',
        'status',
        'assigned_to',
        'approved_by',
        'snapshot_at',
        'completed_at',
        'posted_at',
        'notes',
    ];

    protected $casts = [
        'snapshot_at' => 'datetime',
        'completed_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CycleCountItem::class);
    }

    public function isBlind(): bool
    {
        return $this->count_type === self::TYPE_BLIND;
    }

    public function canStart(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canApprove(): bool
    {
        return in_array($this->status, [self::STATUS_COUNTED, self::STATUS_REVIEW], true);
    }

    public function canPost(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
