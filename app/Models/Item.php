<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory, HasUuids;

    public const CATEGORY_RAW_RESIN = 'RAW_RESIN';

    public const CATEGORY_MASTERBATCH = 'MASTERBATCH';

    public const CATEGORY_PACKAGING = 'PACKAGING';

    public const CATEGORY_FINISHED_GOOD = 'FINISHED_GOOD';

    protected $fillable = [
        'sku',
        'name',
        'category',
        'unit_of_measure',
        'standard_pack_qty',
        'minimum_safety_stock',
        'shelf_life_days',
        'is_active',
    ];

    protected $casts = [
        'standard_pack_qty' => 'decimal:2',
        'minimum_safety_stock' => 'decimal:2',
        'shelf_life_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function lots(): HasMany
    {
        return $this->hasMany(MaterialLot::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
