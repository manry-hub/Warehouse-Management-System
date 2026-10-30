<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerRma extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING_RECEIPT = 'PENDING_RECEIPT';

    public const STATUS_RECEIVED = 'RECEIVED';

    public const STATUS_UNDER_INSPECTION = 'UNDER_INSPECTION';

    public const STATUS_RESOLVED = 'RESOLVED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const DISPOSITION_PENDING = 'PENDING';

    public const DISPOSITION_RESTOCK = 'RESTOCK';

    public const DISPOSITION_SCRAP_REGRIND = 'SCRAP_REGRIND';

    public const DISPOSITION_SCRAP_DISPOSAL = 'SCRAP_DISPOSAL';

    protected $fillable = [
        'rma_number',
        'customer_name',
        'delivery_order_id',
        'status',
        'disposition',
        'total_cartons',
        'received_cartons',
        'defect_reason',
        'qc_inspection_notes',
        'inspected_by',
        'inspected_at',
        'credit_memo_number',
    ];

    protected $casts = [
        'total_cartons' => 'integer',
        'received_cartons' => 'integer',
        'inspected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomerRma $rma) {
            if (empty($rma->rma_number)) {
                $rma->rma_number = sprintf(
                    'RMA-%s-%03d',
                    now()->format('Ymd'),
                    CustomerRma::whereDate('created_at', today())->count() + 1
                );
            }
        });
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerRmaItem::class, 'customer_rma_id');
    }
}
