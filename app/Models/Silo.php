<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Silo extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_NORMAL = 'NORMAL';

    public const STATUS_WARNING = 'WARNING';

    public const STATUS_CRITICAL = 'CRITICAL';

    public const STATUS_OFFLINE = 'OFFLINE';

    protected $fillable = [
        'warehouse_id',
        'zone_id',
        'bin_id',
        'item_id',
        'code',
        'name',
        'total_height_m',
        'diameter_m',
        'cone_height_m',
        'sensor_deadband_m',
        'bulk_density_kg_m3',
        'max_capacity_kg',
        'reorder_threshold_kg',
        'critical_threshold_kg',
        'current_distance_m',
        'current_weight_kg',
        'current_fill_percentage',
        'status',
        'last_telemetry_at',
        'is_active',
    ];

    protected $casts = [
        'total_height_m' => 'decimal:2',
        'diameter_m' => 'decimal:2',
        'cone_height_m' => 'decimal:2',
        'sensor_deadband_m' => 'decimal:2',
        'bulk_density_kg_m3' => 'decimal:2',
        'max_capacity_kg' => 'decimal:2',
        'reorder_threshold_kg' => 'decimal:2',
        'critical_threshold_kg' => 'decimal:2',
        'current_distance_m' => 'decimal:2',
        'current_weight_kg' => 'decimal:2',
        'current_fill_percentage' => 'decimal:2',
        'last_telemetry_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class, 'zone_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'bin_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(SiloTelemetryReading::class)->orderByDesc('recorded_at');
    }

    public function purchaseRequisitions(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class)->orderByDesc('created_at');
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_NORMAL => 'success',
            self::STATUS_WARNING => 'warning',
            self::STATUS_CRITICAL => 'danger',
            default => 'gray',
        };
    }
}
