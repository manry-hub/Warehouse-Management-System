<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityQuarantine extends Model
{
    use HasFactory, HasUuids;

    public const SEVERITY_CRITICAL = 'CRITICAL';

    public const SEVERITY_MAJOR = 'MAJOR';

    public const SEVERITY_MINOR = 'MINOR';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_RESOLVED = 'RESOLVED';

    public const STATUS_SCRAPPED = 'SCRAPPED';

    protected $fillable = [
        'quarantine_code',
        'initiator_id',
        'trigger_lot_id',
        'reason',
        'severity',
        'impacted_lots_count',
        'impacted_total_quantity',
        'status',
        'resolution_notes',
    ];

    protected $casts = [
        'impacted_lots_count' => 'integer',
        'impacted_total_quantity' => 'decimal:3',
    ];

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function triggerLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'trigger_lot_id');
    }
}
