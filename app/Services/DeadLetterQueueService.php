<?php

namespace App\Services;

use App\Jobs\ProcessWebhookJob;
use App\Models\DeadLetterQueueEvent;
use App\Models\WebhookEvent;
use Illuminate\Support\Carbon;
use Throwable;

class DeadLetterQueueService
{
    /**
     * Capture permanently failed webhook job into DLQ database repository.
     */
    public function captureFailedJob(WebhookEvent $event, Throwable $exception): DeadLetterQueueEvent
    {
        /** @var DeadLetterQueueEvent $dlqRecord */
        $dlqRecord = DeadLetterQueueEvent::updateOrCreate(
            ['webhook_event_id' => $event->id],
            [
                'provider' => $event->provider,
                'event_type' => $event->event_type,
                'payload' => $event->payload,
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
                'stack_trace' => $exception->getTraceAsString(),
                'failed_at' => Carbon::now(),
                'status' => DeadLetterQueueEvent::STATUS_UNRESOLVED,
            ]
        );

        if (class_exists(WebhookAlertManager::class)) {
            /** @var WebhookAlertManager $alertManager */
            $alertManager = app(WebhookAlertManager::class);
            $alertManager->handleDlqCapture($dlqRecord);
        }

        return $dlqRecord;
    }

    /**
     * Replay a failed DLQ event by resetting event status and re-dispatching job.
     */
    public function replayEvent(DeadLetterQueueEvent $dlqEvent, ?string $replayedBy = null, int $delaySeconds = 0): bool
    {
        /** @var WebhookEvent|null $webhookEvent */
        $webhookEvent = $dlqEvent->webhookEvent;

        if (! $webhookEvent) {
            return false;
        }

        // Reset payload failure flags if present
        $payload = $webhookEvent->payload;
        if (isset($payload['should_fail'])) {
            unset($payload['should_fail']);
            $webhookEvent->payload = $payload;
        }

        $webhookEvent->status = WebhookEvent::STATUS_PENDING;
        $webhookEvent->retry_count = 0;
        $webhookEvent->error_message = null;
        $webhookEvent->save();

        $dlqEvent->update([
            'status' => DeadLetterQueueEvent::STATUS_REPLAYED,
            'replayed_at' => Carbon::now(),
            'replayed_by' => $replayedBy ?? 'system_admin',
        ]);

        $pendingJob = ProcessWebhookJob::dispatch($webhookEvent);
        if ($delaySeconds > 0) {
            $pendingJob->delay(now()->addSeconds($delaySeconds));
        }

        return true;
    }

    /**
     * Replay unresolved DLQ events in bulk with exponential backoff delays to prevent thundering herd.
     *
     * @param  array<int>  $dlqIds
     */
    public function replayBulkWithExponentialBackoff(array $dlqIds = [], ?string $replayedBy = null): int
    {
        $query = DeadLetterQueueEvent::where('status', DeadLetterQueueEvent::STATUS_UNRESOLVED);

        if (! empty($dlqIds)) {
            $query->whereIn('id', $dlqIds);
        }

        $unresolvedEvents = $query->get();
        $replayedCount = 0;

        foreach ($unresolvedEvents as $index => $dlqEvent) {
            // Exponential delay schedule per item: 0s, 10s, 30s, 90s, 270s... capped at 300s
            $delaySeconds = (int) min(300, 10 * (int) pow(3, min($index, 5)));

            if ($this->replayEvent($dlqEvent, $replayedBy ?? 'bulk_exponential_retry', $delaySeconds)) {
                $replayedCount++;
            }
        }

        return $replayedCount;
    }

    /**
     * Ignore/Dismiss a failed DLQ event.
     */
    public function ignoreEvent(DeadLetterQueueEvent $dlqEvent): void
    {
        $dlqEvent->update([
            'status' => DeadLetterQueueEvent::STATUS_IGNORED,
        ]);
    }
}
