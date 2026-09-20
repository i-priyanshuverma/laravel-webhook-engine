<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\CircuitBreakerService;
use App\Services\DeadLetterQueueService;
use App\Services\RedisIdempotencyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 4;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 90, 300];

    public function __construct(
        public WebhookEvent $webhookEvent
    ) {
        $this->queue = $this->determineQueue($webhookEvent);
    }

    /**
     * Calculate dynamic exponential backoff with randomized jitter.
     */
    public function backoff(): int
    {
        $attempt = max(1, $this->attempts());
        $baseDelay = (int) (10 * pow(2, $attempt - 1));
        $jitter = rand(1, 5);

        return min(300, $baseDelay + $jitter);
    }

    public function handle(RedisIdempotencyService $idempotencyService, CircuitBreakerService $circuitBreaker): void
    {
        $provider = $this->webhookEvent->provider;

        // Circuit breaker check — if the circuit is open, release back to queue with delay
        if (! $circuitBreaker->isAvailable($provider)) {
            Log::warning(sprintf(
                '[ProcessWebhookJob] Circuit OPEN for provider %s — delaying event %s by 30s',
                $provider,
                $this->webhookEvent->event_id
            ));

            $this->release(30);

            return;
        }

        Log::info(sprintf(
            '[ProcessWebhookJob] Processing event %s (provider: %s, attempt: %d/%d)',
            $this->webhookEvent->event_id,
            $provider,
            $this->attempts(),
            $this->tries
        ), [
            'event_id' => $this->webhookEvent->event_id,
            'provider' => $provider,
            'event_type' => $this->webhookEvent->event_type,
            'attempt' => $this->attempts(),
        ]);

        $this->webhookEvent->update([
            'status' => WebhookEvent::STATUS_PROCESSING,
            'retry_count' => $this->attempts(),
        ]);

        if (isset($this->webhookEvent->payload['should_fail']) && $this->webhookEvent->payload['should_fail'] === true) {
            throw new \RuntimeException('Simulated processing failure for event: '.$this->webhookEvent->event_id);
        }

        $this->webhookEvent->update([
            'status' => WebhookEvent::STATUS_COMPLETED,
            'processed_at' => Carbon::now(),
            'error_message' => null,
        ]);

        $idempotencyService->markProcessed($provider, $this->webhookEvent->event_id);

        // Record success for circuit breaker (matters during half-open state)
        $circuitBreaker->recordSuccess($provider);
    }

    public function failed(Throwable $exception): void
    {
        Log::error(sprintf(
            '[ProcessWebhookJob] Permanently failed event %s (provider: %s): %s',
            $this->webhookEvent->event_id,
            $this->webhookEvent->provider,
            $exception->getMessage()
        ), [
            'event_id' => $this->webhookEvent->event_id,
            'provider' => $this->webhookEvent->provider,
            'exception' => $exception->getMessage(),
        ]);

        $this->webhookEvent->update([
            'status' => WebhookEvent::STATUS_FAILED,
            'error_message' => $exception->getMessage(),
        ]);

        // Record failure for circuit breaker
        /** @var CircuitBreakerService $circuitBreaker */
        $circuitBreaker = app(CircuitBreakerService::class);
        $circuitBreaker->recordFailure($this->webhookEvent->provider);

        /** @var DeadLetterQueueService $dlqService */
        $dlqService = app(DeadLetterQueueService::class);
        $dlqService->captureFailedJob($this->webhookEvent, $exception);
    }

    private function determineQueue(WebhookEvent $event): string
    {
        $eventType = strtolower($event->event_type);
        if (str_contains($eventType, 'charge') || str_contains($eventType, 'payment') || str_contains($eventType, 'order')) {
            return 'high';
        }

        if (str_contains($eventType, 'sync') || str_contains($eventType, 'analytics')) {
            return 'low';
        }

        return 'default';
    }
}
