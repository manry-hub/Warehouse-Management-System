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
        'allow_regrind',
        'max_regrind_percentage',
        'is_active',
    ];

    protected $casts = [
        'standard_pack_qty' => 'decimal:2',
        'minimum_safety_stock' => 'decimal:2',
        'shelf_life_days' => 'integer',
        'allow_regrind' => 'boolean',
        'max_regrind_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function allowsRegrind(): bool
    {
        return (bool) $this->allow_regrind;
    }

    public function isRawMaterial(): bool
    {
        return in_array($this->category, [self::CATEGORY_RAW_RESIN, self::CATEGORY_MASTERBATCH]);
    }

    public function isFinishedGood(): bool
    {
        return $this->category === self::CATEGORY_FINISHED_GOOD;
    }

    public function getMaxRegrindRatio(): float
    {
        return $this->allowsRegrind() ? (float) $this->max_regrind_percentage : 0.0;
    }

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
