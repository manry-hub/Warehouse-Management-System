<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseZone extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_RAW_MATERIAL = 'RAW_MATERIAL';

    public const TYPE_FINISHED_GOODS = 'FINISHED_GOODS';

    public const TYPE_STAGING_BUFFER = 'STAGING_BUFFER';

    public const TYPE_QUARANTINE = 'QUARANTINE';

    public const TYPE_SILO = 'SILO';

    public const TYPE_IN_TRANSIT = 'IN_TRANSIT';

    protected $fillable = [
        'warehouse_id',
        'code',
        'name',
        'zone_type',
        'is_temperature_controlled',
    ];

    protected $casts = [
        'is_temperature_controlled' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function bins(): HasMany
    {
        return $this->hasMany(WarehouseBin::class, 'zone_id');
    }
}
