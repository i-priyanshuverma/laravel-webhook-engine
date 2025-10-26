<?php

namespace Tests\Unit;

use App\Jobs\ProcessWebhookJob;
use App\Models\DeadLetterQueueEvent;
use App\Models\WebhookEvent;
use App\Services\RedisIdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProcessWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_processes_webhook_successfully(): void
    {
        $event = WebhookEvent::create([
            'event_id' => 'evt_unit_001',
            'provider' => 'stripe',
            'event_type' => 'payment_intent.succeeded',
            'payload' => ['amount' => 1000],
            'status' => WebhookEvent::STATUS_PENDING,
        ]);

        $mockIdempotency = $this->createMock(RedisIdempotencyService::class);
        $mockIdempotency->expects($this->once())
            ->method('markProcessed')
            ->with('stripe', 'evt_unit_001');

        $job = new ProcessWebhookJob($event);
        $job->handle($mockIdempotency);

        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_COMPLETED, $event->status);
        $this->assertNotNull($event->processed_at);
    }

    public function test_job_routing_assigns_correct_queues(): void
    {
        $highPriorityEvent = WebhookEvent::create([
            'event_id' => 'evt_charge_1',
            'provider' => 'stripe',
            'event_type' => 'charge.succeeded',
            'payload' => [],
        ]);

        $jobHigh = new ProcessWebhookJob($highPriorityEvent);
        $this->assertEquals('high', $jobHigh->queue);

        $defaultPriorityEvent = WebhookEvent::create([
            'event_id' => 'evt_user_1',
            'provider' => 'generic',
            'event_type' => 'user.updated',
            'payload' => [],
        ]);

        $jobDefault = new ProcessWebhookJob($defaultPriorityEvent);
        $this->assertEquals('default', $jobDefault->queue);
    }

    public function test_job_failed_handler_captures_dlq_record(): void
    {
        $event = WebhookEvent::create([
            'event_id' => 'evt_failed_001',
            'provider' => 'shopify',
            'event_type' => 'orders/create',
            'payload' => ['should_fail' => true],
            'status' => WebhookEvent::STATUS_PENDING,
        ]);

        $exception = new RuntimeException('Job execution failed completely');
        $job = new ProcessWebhookJob($event);
        $job->failed($exception);

        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_FAILED, $event->status);
        $this->assertEquals('Job execution failed completely', $event->error_message);

        $this->assertDatabaseHas('dead_letter_queue_events', [
            'webhook_event_id' => $event->id,
            'provider' => 'shopify',
            'status' => DeadLetterQueueEvent::STATUS_UNRESOLVED,
        ]);
    }
}
