<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\DeadLetterQueueService;
use App\Services\RedisIdempotencyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
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

    public function handle(RedisIdempotencyService $idempotencyService): void
    {
        $this->webhookEvent->update([
            'status' => WebhookEvent::STATUS_PROCESSING,
            'retry_count' => $this->attempts(),
        ]);

        // Process webhook logic (e.g. business logic, third party dispatches)
        // Here we simulate successful processing or throwing exception if payload triggers error
        if (isset($this->webhookEvent->payload['should_fail']) && $this->webhookEvent->payload['should_fail'] === true) {
            throw new \RuntimeException('Simulated processing failure for event: ' . $this->webhookEvent->event_id);
        }

        $this->webhookEvent->update([
            'status' => WebhookEvent::STATUS_COMPLETED,
            'processed_at' => Carbon::now(),
            'error_message' => null,
        ]);

        $idempotencyService->markProcessed(
            $this->webhookEvent->provider,
            $this->webhookEvent->event_id
        );
    }

    public function failed(Throwable $exception): void
    {
        $this->webhookEvent->update([
            'status' => WebhookEvent::STATUS_FAILED,
            'error_message' => $exception->getMessage(),
        ]);

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
