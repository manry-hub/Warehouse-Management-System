<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialLot extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_QA_HOLD = 'QA_HOLD';

    public const STATUS_AVAILABLE = 'AVAILABLE';

    public const STATUS_EXPIRED = 'EXPIRED';

    public const STATUS_QUARANTINE = 'QUARANTINE';

    protected $fillable = [
        'lot_number',
        'item_id',
        'vendor_code',
        'vendor_lot_number',
        'vendor_heat_number',
        'manufactured_date',
        'expiry_date',
        'coa_file_path',
        'coa_metrics',
        'status',
        'qc_inspected_at',
        'qc_inspected_by',
    ];

    protected $casts = [
        'manufactured_date' => 'date',
        'expiry_date' => 'date',
        'coa_metrics' => 'array',
        'qc_inspected_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qc_inspected_by');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }
}
