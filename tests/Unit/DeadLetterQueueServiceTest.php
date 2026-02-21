<?php

namespace Tests\Unit;

use App\Jobs\ProcessWebhookJob;
use App\Models\DeadLetterQueueEvent;
use App\Models\WebhookEvent;
use App\Services\DeadLetterQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class DeadLetterQueueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dlq_service_captures_failed_job(): void
    {
        $event = WebhookEvent::create([
            'event_id' => 'evt_dlq_test_1',
            'provider' => 'stripe',
            'event_type' => 'invoice.payment_failed',
            'payload' => ['id' => 'inv_123'],
            'status' => WebhookEvent::STATUS_FAILED,
        ]);

        $dlqService = new DeadLetterQueueService;
        $exception = new RuntimeException('Connection timed out');

        $record = $dlqService->captureFailedJob($event, $exception);

        $this->assertEquals($event->id, $record->webhook_event_id);
        $this->assertEquals('stripe', $record->provider);
        $this->assertEquals('Connection timed out', $record->exception_message);
        $this->assertEquals(DeadLetterQueueEvent::STATUS_UNRESOLVED, $record->status);
    }

    public function test_dlq_service_replays_event(): void
    {
        Queue::fake();

        $event = WebhookEvent::create([
            'event_id' => 'evt_dlq_replay_1',
            'provider' => 'stripe',
            'event_type' => 'charge.succeeded',
            'payload' => ['amount' => 5000],
            'status' => WebhookEvent::STATUS_FAILED,
        ]);

        $dlqEvent = DeadLetterQueueEvent::create([
            'webhook_event_id' => $event->id,
            'provider' => 'stripe',
            'event_type' => 'charge.succeeded',
            'payload' => ['amount' => 5000],
            'exception_class' => RuntimeException::class,
            'exception_message' => 'Failed transiently',
            'stack_trace' => '#0 dummy trace',
            'failed_at' => now(),
            'status' => DeadLetterQueueEvent::STATUS_UNRESOLVED,
        ]);

        $dlqService = new DeadLetterQueueService;
        $result = $dlqService->replayEvent($dlqEvent, 'admin_user');

        $this->assertTrue($result);

        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_PENDING, $event->status);

        $dlqEvent->refresh();
        $this->assertEquals(DeadLetterQueueEvent::STATUS_REPLAYED, $dlqEvent->status);
        $this->assertEquals('admin_user', $dlqEvent->replayed_by);

        Queue::assertPushed(ProcessWebhookJob::class, function ($job) use ($event) {
            return $job->webhookEvent->id === $event->id;
        });
    }

    public function test_dlq_service_replays_bulk_with_exponential_backoff(): void
    {
        Queue::fake();

        $event1 = WebhookEvent::create([
            'event_id' => 'evt_bulk_1',
            'provider' => 'stripe',
            'event_type' => 'charge.failed',
            'payload' => [],
            'status' => WebhookEvent::STATUS_FAILED,
        ]);

        $dlq1 = DeadLetterQueueEvent::create([
            'webhook_event_id' => $event1->id,
            'provider' => 'stripe',
            'event_type' => 'charge.failed',
            'payload' => [],
            'exception_class' => RuntimeException::class,
            'exception_message' => 'Failed 1',
            'stack_trace' => 'trace',
            'failed_at' => now(),
            'status' => DeadLetterQueueEvent::STATUS_UNRESOLVED,
        ]);

        $dlqService = new DeadLetterQueueService;
        $count = $dlqService->replayBulkWithExponentialBackoff([$dlq1->id], 'bulk_admin');

        $this->assertEquals(1, $count);
        $this->assertEquals(DeadLetterQueueEvent::STATUS_REPLAYED, $dlq1->fresh()->status);
        Queue::assertPushed(ProcessWebhookJob::class);
    }
}
