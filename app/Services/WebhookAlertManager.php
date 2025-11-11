<?php

namespace App\Services;

use App\Models\DeadLetterQueueEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookAlertManager
{
    public const ALERT_THRESHOLD = 5;

    public function handleDlqCapture(DeadLetterQueueEvent $dlqEvent): void
    {
        $unresolvedCount = DeadLetterQueueEvent::where('status', DeadLetterQueueEvent::STATUS_UNRESOLVED)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        $eventId = $dlqEvent->webhookEvent ? $dlqEvent->webhookEvent->event_id : (string) $dlqEvent->id;

        Log::error(sprintf(
            '[DLQ Alert] Webhook failure captured for provider %s event %s: %s',
            $dlqEvent->provider,
            $eventId,
            $dlqEvent->exception_message
        ), [
            'dlq_event_id' => $dlqEvent->id,
            'exception_class' => $dlqEvent->exception_class,
            'unresolved_count_15m' => $unresolvedCount,
        ]);

        $this->sendSentryAlert($dlqEvent);

        if ($unresolvedCount >= self::ALERT_THRESHOLD) {
            $slackUrl = config('services.slack.webhook_url');
            if (! empty($slackUrl)) {
                $this->sendSlackNotification(
                    sprintf(
                        '⚠️ *DLQ Threshold Exceeded!* %d unresolved webhook failures in the last 15 mins. Latest provider: *%s*',
                        $unresolvedCount,
                        strtoupper($dlqEvent->provider)
                    ),
                    [
                        'event_type' => $dlqEvent->event_type,
                        'exception' => $dlqEvent->exception_message,
                    ],
                    (string) $slackUrl
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendSlackNotification(string $message, array $context, string $webhookUrl): void
    {
        try {
            Http::post($webhookUrl, [
                'text' => $message,
                'attachments' => [
                    [
                        'color' => '#FF0000',
                        'fields' => array_map(fn ($k, $v) => [
                            'title' => ucfirst($k),
                            'value' => (string) $v,
                            'short' => true,
                        ], array_keys($context), array_values($context)),
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to send Slack alert: '.$e->getMessage());
        }
    }

    public function sendSentryAlert(DeadLetterQueueEvent $dlqEvent): void
    {
        if (function_exists('sentry_capture_message')) {
            sentry_capture_message(
                "DLQ Capture: {$dlqEvent->provider} - {$dlqEvent->exception_message}"
            );
        }
    }
}
