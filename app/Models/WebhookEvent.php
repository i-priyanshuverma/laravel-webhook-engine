<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $event_id
 * @property string $provider
 * @property string $event_type
 * @property array $payload
 * @property string $status
 * @property int $retry_count
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class WebhookEvent extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'event_id',
        'provider',
        'event_type',
        'payload',
        'status',
        'retry_count',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'retry_count' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }

    public function deadLetterQueueEvent(): HasOne
    {
        return $this->hasOne(DeadLetterQueueEvent::class);
    }
}
