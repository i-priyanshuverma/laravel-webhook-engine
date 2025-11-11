<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $webhook_event_id
 * @property string $provider
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property string $exception_class
 * @property string $exception_message
 * @property string $stack_trace
 * @property Carbon $failed_at
 * @property Carbon|null $replayed_at
 * @property string|null $replayed_by
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WebhookEvent|null $webhookEvent
 *
 * @use HasFactory<Factory<DeadLetterQueueEvent>>
 */
class DeadLetterQueueEvent extends Model
{
    use HasFactory;

    public const STATUS_UNRESOLVED = 'unresolved';

    public const STATUS_REPLAYED = 'replayed';

    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'webhook_event_id',
        'provider',
        'event_type',
        'payload',
        'exception_class',
        'exception_message',
        'stack_trace',
        'failed_at',
        'replayed_at',
        'replayed_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'failed_at' => 'datetime',
            'replayed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WebhookEvent, $this>
     */
    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class);
    }
}
