<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRmaItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'customer_rma_id',
        'item_id',
        'material_lot_id',
        'carton_barcode',
        'quantity_returned',
        'quantity_received',
        'disposition',
    ];

    protected $casts = [
        'quantity_returned' => 'decimal:3',
        'quantity_received' => 'decimal:3',
    ];

    public function rma(): BelongsTo
    {
        return $this->belongsTo(CustomerRma::class, 'customer_rma_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id');
    }
}
