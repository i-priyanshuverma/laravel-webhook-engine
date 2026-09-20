<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $event_id
 * @property string $provider
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property string|null $encrypted_payload
 * @property string $status
 * @property int $retry_count
 * @property string|null $error_message
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @use HasFactory<Factory<WebhookEvent>>
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
        'encrypted_payload',
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

    /**
     * @return HasMany<WebhookLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }

    /**
     * @return HasOne<DeadLetterQueueEvent, $this>
     */
    public function deadLetterQueueEvent(): HasOne
    {
        return $this->hasOne(DeadLetterQueueEvent::class);
    }
}
