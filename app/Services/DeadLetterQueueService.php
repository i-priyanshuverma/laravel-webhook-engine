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
    public function replayEvent(DeadLetterQueueEvent $dlqEvent, ?string $replayedBy = null): bool
    {
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

        ProcessWebhookJob::dispatch($webhookEvent);

        return true;
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
