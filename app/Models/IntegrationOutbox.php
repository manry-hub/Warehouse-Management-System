<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationOutbox extends Model
{
    use HasFactory;

    protected $table = 'integration_outbox';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_PROCESSING = 'PROCESSING';

    public const STATUS_PROCESSED = 'PROCESSED';

    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'event_uuid',
        'event_type',
        'payload',
        'status',
        'attempts',
        'last_error',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'processed_at' => 'datetime',
    ];
}
