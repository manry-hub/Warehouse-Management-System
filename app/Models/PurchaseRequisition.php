<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequisition extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_ORDERED = 'ORDERED';

    public const STATUS_FULFILLED = 'FULFILLED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const REQUESTER_SYSTEM = 'SYSTEM_AUTO_REORDER';

    public const REQUESTER_MANUAL = 'MANUAL_OPERATOR';

    protected $fillable = [
        'pr_number',
        'silo_id',
        'item_id',
        'requested_by_type',
        'requested_quantity_kg',
        'status',
        'notes',
        'approved_at',
        'fulfilled_at',
    ];

    protected $casts = [
        'requested_quantity_kg' => 'decimal:2',
        'approved_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    public function silo(): BelongsTo
    {
        return $this->belongsTo(Silo::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_APPROVED,
            self::STATUS_ORDERED,
        ], true);
    }
}
