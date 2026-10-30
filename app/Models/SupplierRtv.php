<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierRtv extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_DISPATCHED = 'DISPATCHED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'rtv_number',
        'vendor_code',
        'vendor_name',
        'po_number',
        'material_lot_id',
        'warehouse_bin_id',
        'quantity_kg',
        'reason',
        'status',
        'debit_memo_number',
        'dispatched_at',
        'dispatched_by',
    ];

    protected $casts = [
        'quantity_kg' => 'decimal:4',
        'dispatched_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupplierRtv $rtv) {
            if (empty($rtv->rtv_number)) {
                $rtv->rtv_number = sprintf(
                    'RTV-%s-%03d',
                    now()->format('Ymd'),
                    SupplierRtv::whereDate('created_at', today())->count() + 1
                );
            }
        });
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id');
    }

    public function warehouseBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'warehouse_bin_id');
    }

    public function dispatchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }
}
