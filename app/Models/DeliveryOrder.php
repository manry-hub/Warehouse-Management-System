<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryOrder extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PICKING = 'PICKING';

    public const STATUS_LOADED = 'LOADED';

    public const STATUS_DISPATCHED = 'DISPATCHED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'do_number',
        'customer_name',
        'shipping_address',
        'vehicle_plate_number',
        'driver_name',
        'status',
        'total_cartons',
        'dispatched_at',
    ];

    protected $casts = [
        'total_cartons' => 'integer',
        'dispatched_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }
}
