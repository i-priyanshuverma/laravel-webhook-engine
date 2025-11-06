<?php

namespace Tests\Feature;

use App\Livewire\DashboardMetrics;
use App\Livewire\DeadLetterQueueManager;
use App\Livewire\WebhookLogTable;
use App\Models\DeadLetterQueueEvent;
use App\Models\WebhookEvent;
use App\Models\WebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireComponentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_metrics_component_renders_metrics(): void
    {
        WebhookEvent::create([
            'event_id' => 'evt_test_dash_1',
            'provider' => 'stripe',
            'event_type' => 'charge.succeeded',
            'payload' => [],
            'status' => WebhookEvent::STATUS_COMPLETED,
        ]);

        Livewire::test(DashboardMetrics::class)
            ->assertStatus(200)
            ->assertSee('Real-Time Queue')
            ->assertSee('stripe');
    }

    public function test_dlq_manager_component_allows_replay_and_filters(): void
    {
        $webhookEvent = WebhookEvent::create([
            'event_id' => 'evt_dlq_livewire_1',
            'provider' => 'shopify',
            'event_type' => 'orders/create',
            'payload' => [],
            'status' => WebhookEvent::STATUS_FAILED,
        ]);

        $dlqEvent = DeadLetterQueueEvent::create([
            'webhook_event_id' => $webhookEvent->id,
            'provider' => 'shopify',
            'event_type' => 'orders/create',
            'payload' => [],
            'exception_class' => \RuntimeException::class,
            'exception_message' => 'Simulated Livewire Failure',
            'stack_trace' => 'trace details',
            'failed_at' => now(),
            'status' => DeadLetterQueueEvent::STATUS_UNRESOLVED,
        ]);

        Livewire::test(DeadLetterQueueManager::class)
            ->assertSee('Simulated Livewire Failure')
            ->call('replay', $dlqEvent->id)
            ->assertSee('successfully queued for re-processing');

        $this->assertEquals(DeadLetterQueueEvent::STATUS_REPLAYED, $dlqEvent->fresh()->status);
    }

    public function test_webhook_log_table_filters_and_searches(): void
    {
        WebhookLog::create([
            'provider' => 'stripe',
            'event_id' => 'evt_log_search_1',
            'http_method' => 'POST',
            'headers' => [],
            'payload' => ['foo' => 'bar'],
            'ip_address' => '127.0.0.1',
            'response_code' => 202,
            'execution_time_ms' => 12.5,
        ]);

        Livewire::test(WebhookLogTable::class)
            ->set('search', 'evt_log_search_1')
            ->assertSee('evt_log_search_1')
            ->set('providerFilter', 'shopify')
            ->assertDontSee('evt_log_search_1');
    }
}
