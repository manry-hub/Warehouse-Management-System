<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiloTelemetryReading extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    public const SENSOR_RADAR_80GHZ = 'RADAR_80GHZ';

    public const SENSOR_ULTRASONIC = 'ULTRASONIC';

    public const SENSOR_PRESSURE = 'PRESSURE_TRANSMITTER';

    protected $fillable = [
        'silo_id',
        'sensor_type',
        'distance_m',
        'material_height_m',
        'volume_m3',
        'weight_kg',
        'fill_percentage',
        'temperature_c',
        'battery_percentage',
        'raw_payload',
        'recorded_at',
    ];

    protected $casts = [
        'distance_m' => 'decimal:2',
        'material_height_m' => 'decimal:2',
        'volume_m3' => 'decimal:3',
        'weight_kg' => 'decimal:2',
        'fill_percentage' => 'decimal:2',
        'temperature_c' => 'decimal:2',
        'battery_percentage' => 'decimal:2',
        'raw_payload' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function silo(): BelongsTo
    {
        return $this->belongsTo(Silo::class);
    }
}
