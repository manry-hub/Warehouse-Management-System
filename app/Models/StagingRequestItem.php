<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StagingRequestItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'staging_request_id',
        'item_id',
        'material_lot_id',
        'quantity_requested',
        'quantity_picked',
        'is_regrind',
        'blend_percentage',
        'source_bin_id',
        'staging_bin_id',
        'status',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:3',
        'quantity_picked' => 'decimal:3',
        'is_regrind' => 'boolean',
        'blend_percentage' => 'decimal:2',
    ];

    public function stagingRequest(): BelongsTo
    {
        return $this->belongsTo(StagingRequest::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id');
    }

    public function sourceBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'source_bin_id');
    }

    public function stagingBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'staging_bin_id');
    }
}
