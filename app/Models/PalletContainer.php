<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PalletContainer extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_OPEN = 'OPEN';

    public const STATUS_SEALED = 'SEALED';

    public const STATUS_STAGED = 'STAGED';

    public const STATUS_LOADED = 'LOADED';

    protected $fillable = [
        'sscc_code',
        'delivery_order_id',
        'item_id',
        'material_lot_id',
        'carton_count',
        'gross_weight_kg',
        'tare_weight_kg',
        'net_weight_kg',
        'status',
    ];

    protected $casts = [
        'carton_count' => 'integer',
        'gross_weight_kg' => 'decimal:3',
        'tare_weight_kg' => 'decimal:3',
        'net_weight_kg' => 'decimal:3',
    ];

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id');
    }
}
