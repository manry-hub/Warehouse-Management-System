<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'delivery_order_id',
        'item_id',
        'material_lot_id',
        'carton_barcode',
        'quantity_ordered',
        'quantity_loaded',
        'is_verified_scanned',
        'source_bin_id',
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:3',
        'quantity_loaded' => 'decimal:3',
        'is_verified_scanned' => 'boolean',
    ];

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

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
}
