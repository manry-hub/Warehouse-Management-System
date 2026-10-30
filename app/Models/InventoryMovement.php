<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    public const UPDATED_AT = null; // Append-only immutable log

    public const TYPE_RECEIPT = 'RECEIPT';

    public const TYPE_PUTAWAY = 'PUTAWAY';

    public const TYPE_STAGING_ISSUE = 'STAGING_ISSUE';

    public const TYPE_FG_INTAKE = 'FG_INTAKE';

    public const TYPE_SHIPMENT = 'SHIPMENT';

    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';

    protected $fillable = [
        'transaction_uuid',
        'movement_type',
        'item_id',
        'material_lot_id',
        'source_bin_id',
        'destination_bin_id',
        'quantity',
        'uom',
        'reference_type',
        'reference_id',
        'user_id',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'created_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id');
    }

    public function sourceBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'source_bin_id');
    }

    public function destinationBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'destination_bin_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
