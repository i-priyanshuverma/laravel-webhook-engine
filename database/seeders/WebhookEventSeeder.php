<?php

namespace Database\Seeders;

use App\Models\DeadLetterQueueEvent;
use App\Models\WebhookEvent;
use App\Models\WebhookLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class WebhookEventSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Stripe Charge Succeeded
        $stripeEvent1 = WebhookEvent::create([
            'event_id' => 'evt_stripe_seed_001',
            'provider' => 'stripe',
            'event_type' => 'charge.succeeded',
            'payload' => [
                'id' => 'evt_stripe_seed_001',
                'type' => 'charge.succeeded',
                'data' => [
                    'object' => [
                        'amount' => 14900,
                        'currency' => 'usd',
                        'customer' => 'cus_N9s8F002',
                        'paid' => true,
                    ],
                ],
            ],
            'status' => WebhookEvent::STATUS_COMPLETED,
            'retry_count' => 1,
            'processed_at' => $now->subMinutes(30),
        ]);

        WebhookLog::create([
            'webhook_event_id' => $stripeEvent1->id,
            'provider' => 'stripe',
            'event_id' => 'evt_stripe_seed_001',
            'http_method' => 'POST',
            'headers' => ['stripe-signature' => 't=169000000,v1=abcdef12345'],
            'payload' => $stripeEvent1->payload,
            'ip_address' => '54.187.205.235',
            'response_code' => 202,
            'execution_time_ms' => 18.4,
        ]);

        // 2. Shopify Order Create
        $shopifyEvent1 = WebhookEvent::create([
            'event_id' => 'shp_seed_987654',
            'provider' => 'shopify',
            'event_type' => 'orders/create',
            'payload' => [
                'id' => 987654,
                'email' => 'customer@example.com',
                'total_price' => '249.99',
                'currency' => 'USD',
                'line_items' => [
                    ['title' => 'Enterprise Webhook Subscription', 'quantity' => 1, 'price' => '249.99'],
                ],
            ],
            'status' => WebhookEvent::STATUS_COMPLETED,
            'retry_count' => 1,
            'processed_at' => $now->subMinutes(15),
        ]);

        WebhookLog::create([
            'webhook_event_id' => $shopifyEvent1->id,
            'provider' => 'shopify',
            'event_id' => 'shp_seed_987654',
            'http_method' => 'POST',
            'headers' => ['x-shopify-hmac-sha256' => 'base64hmac='],
            'payload' => $shopifyEvent1->payload,
            'ip_address' => '35.190.45.12',
            'response_code' => 202,
            'execution_time_ms' => 14.2,
        ]);

        // 3. Failed Stripe Invoice (DLQ Captured)
        $failedStripeEvent = WebhookEvent::create([
            'event_id' => 'evt_stripe_seed_failed_002',
            'provider' => 'stripe',
            'event_type' => 'invoice.payment_failed',
            'payload' => [
                'id' => 'evt_stripe_seed_failed_002',
                'type' => 'invoice.payment_failed',
                'data' => [
                    'object' => [
                        'amount_due' => 9900,
                        'attempt_count' => 4,
                    ],
                ],
            ],
            'status' => WebhookEvent::STATUS_FAILED,
            'retry_count' => 4,
            'error_message' => 'Connection timeout downstream service (HTTP 504 Gateway Timeout)',
            'processed_at' => null,
        ]);

        WebhookLog::create([
            'webhook_event_id' => $failedStripeEvent->id,
            'provider' => 'stripe',
            'event_id' => 'evt_stripe_seed_failed_002',
            'http_method' => 'POST',
            'headers' => ['stripe-signature' => 't=169000000,v1=abcdef999'],
            'payload' => $failedStripeEvent->payload,
            'ip_address' => '54.187.205.235',
            'response_code' => 202,
            'execution_time_ms' => 25.1,
        ]);

        DeadLetterQueueEvent::create([
            'webhook_event_id' => $failedStripeEvent->id,
            'provider' => 'stripe',
            'event_type' => 'invoice.payment_failed',
            'payload' => $failedStripeEvent->payload,
            'exception_class' => \RuntimeException::class,
            'exception_message' => 'Connection timeout downstream service (HTTP 504 Gateway Timeout)',
            'stack_trace' => "#0 App\\Jobs\\ProcessWebhookJob->handle()\n#1 Illuminate\\Queue\\CallQueuedHandler->dispatch()",
            'failed_at' => $now->subMinutes(10),
            'status' => DeadLetterQueueEvent::STATUS_UNRESOLVED,
        ]);

        // 4. Generic Webhook Event
        $genericEvent = WebhookEvent::create([
            'event_id' => 'gen_seed_003',
            'provider' => 'generic',
            'event_type' => 'system.backup_completed',
            'payload' => ['size_bytes' => 1073741824, 'duration_sec' => 45],
            'status' => WebhookEvent::STATUS_COMPLETED,
            'retry_count' => 1,
            'processed_at' => $now->subMinutes(5),
        ]);

        WebhookLog::create([
            'webhook_event_id' => $genericEvent->id,
            'provider' => 'generic',
            'event_id' => 'gen_seed_003',
            'http_method' => 'POST',
            'headers' => ['x-signature' => 'sig_gen_003'],
            'payload' => $genericEvent->payload,
            'ip_address' => '127.0.0.1',
            'response_code' => 202,
            'execution_time_ms' => 8.1,
        ]);
    }
}
