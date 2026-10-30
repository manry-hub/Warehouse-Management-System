<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseBin extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'zone_id',
        'bin_code',
        'aisle',
        'rack',
        'shelf',
        'bin',
        'max_weight_kg',
        'max_volume_cbm',
        'is_blocked',
    ];

    protected $casts = [
        'max_weight_kg' => 'decimal:2',
        'max_volume_cbm' => 'decimal:2',
        'is_blocked' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class, 'zone_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'bin_id');
    }

    public function incomingMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'destination_bin_id');
    }

    public function outgoingMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'source_bin_id');
    }
}
