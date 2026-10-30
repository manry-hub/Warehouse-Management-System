<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferOrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'transfer_order_id',
        'item_id',
        'material_lot_id',
        'origin_bin_id',
        'destination_bin_id',
        'quantity',
        'is_cross_docked',
        'cross_dock_delivery_order_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'is_cross_docked' => 'boolean',
    ];

    public function transferOrder(): BelongsTo
    {
        return $this->belongsTo(TransferOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id');
    }

    public function originBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'origin_bin_id');
    }

    public function destinationBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'destination_bin_id');
    }

    public function crossDockDeliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'cross_dock_delivery_order_id');
    }
}
