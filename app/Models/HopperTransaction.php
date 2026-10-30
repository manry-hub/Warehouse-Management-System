<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HopperTransaction extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_STAGED = 'STAGED';

    public const STATUS_CONSUMING = 'CONSUMING';

    public const STATUS_CLOSED = 'CLOSED';

    protected $fillable = [
        'transaction_code',
        'machine_code',
        'hopper_bin_id',
        'material_lot_id',
        'operator_id',
        'production_order_number',
        'quantity_staged_kg',
        'quantity_returned_kg',
        'status',
        'staged_at',
        'closed_at',
    ];

    protected $casts = [
        'quantity_staged_kg' => 'decimal:3',
        'quantity_returned_kg' => 'decimal:3',
        'staged_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function hopperBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'hopper_bin_id');
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
