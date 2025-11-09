<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $webhook_event_id
 * @property string $provider
 * @property string|null $event_id
 * @property string $http_method
 * @property array<string, mixed>|null $headers
 * @property array<string, mixed>|null $payload
 * @property string|null $ip_address
 * @property int $response_code
 * @property float $execution_time_ms
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<WebhookLog>>
 */
class WebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_event_id',
        'provider',
        'event_id',
        'http_method',
        'headers',
        'payload',
        'ip_address',
        'response_code',
        'execution_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload' => 'array',
            'response_code' => 'integer',
            'execution_time_ms' => 'float',
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
