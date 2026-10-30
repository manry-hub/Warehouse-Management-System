<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplenishmentTask extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_ASSIGNED = 'ASSIGNED';

    public const STATUS_IN_TRANSIT = 'IN_TRANSIT';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const PRIORITY_CRITICAL = 'CRITICAL';

    public const PRIORITY_URGENT = 'URGENT';

    public const PRIORITY_NORMAL = 'NORMAL';

    protected $fillable = [
        'task_number',
        'target_bin_id',
        'source_bin_id',
        'item_id',
        'material_lot_id',
        'requested_quantity_kg',
        'replenished_quantity_kg',
        'status',
        'priority',
        'assigned_to',
        'notes',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'requested_quantity_kg' => 'decimal:4',
        'replenished_quantity_kg' => 'decimal:4',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function targetBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'target_bin_id');
    }

    public function sourceBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'source_bin_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
