<?php

namespace Tests\Feature;

use App\Services\RedisIdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WebhookIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_webhook_ingestion_succeeds(): void
    {
        $payload = [
            'event_id' => 'gen_evt_001',
            'event_type' => 'user.registered',
            'payload' => ['user_id' => 123, 'email' => 'test@example.com'],
        ];

        $response = $this->postJson('/api/v1/webhooks/generic', $payload);

        $response->assertStatus(202)
            ->assertJson([
                'status' => 'success',
                'event_id' => 'gen_evt_001',
                'provider' => 'generic',
            ]);

        $this->assertDatabaseHas('webhook_events', [
            'event_id' => 'gen_evt_001',
            'provider' => 'generic',
        ]);

        $this->assertDatabaseHas('webhook_logs', [
            'event_id' => 'gen_evt_001',
            'response_code' => 202,
        ]);
    }

    public function test_stripe_webhook_with_valid_hmac_signature_succeeds(): void
    {
        $secret = 'whsec_test_secret_key_123';
        Config::set('services.stripe.webhook_secret', $secret);

        $payload = json_encode([
            'id' => 'evt_stripe_test_001',
            'type' => 'charge.succeeded',
            'data' => ['object' => ['amount' => 5000, 'currency' => 'usd']],
        ]);

        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $headerValue = "t={$timestamp},v1={$signature}";

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $headerValue,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(202)
            ->assertJson([
                'status' => 'success',
                'event_id' => 'evt_stripe_test_001',
                'provider' => 'stripe',
            ]);
    }

    public function test_stripe_webhook_fails_with_invalid_signature(): void
    {
        Config::set('services.stripe.webhook_secret', 'whsec_test_secret');

        $payload = json_encode(['id' => 'evt_fail_1', 'type' => 'charge.failed']);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => 't=12345,v1=invalid_signature',
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    public function test_shopify_webhook_with_valid_hmac_succeeds(): void
    {
        $secret = 'shopify_secret_key_999';
        Config::set('services.shopify.webhook_secret', $secret);

        $payload = json_encode(['id' => 987654, 'name' => '#1001', 'total_price' => '199.99']);
        $signature = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/shopify',
            [],
            [],
            [],
            [
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
                'HTTP_X_SHOPIFY_WEBHOOK_ID' => 'shp_evt_987654',
                'HTTP_X_SHOPIFY_TOPIC' => 'orders/create',
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(202)
            ->assertJson([
                'status' => 'success',
                'event_id' => 'shp_evt_987654',
                'provider' => 'shopify',
            ]);
    }

    public function test_duplicate_webhook_returns_409_conflict(): void
    {
        $mockService = $this->createMock(RedisIdempotencyService::class);
        $mockService->method('isProcessed')->willReturn(false);
        $mockService->method('acquireLock')->willReturn(false);
        $this->app->instance(RedisIdempotencyService::class, $mockService);

        $payload = [
            'event_id' => 'duplicate_evt_123',
            'event_type' => 'order.updated',
            'payload' => ['id' => 10],
        ];

        $response = $this->postJson('/api/v1/webhooks/generic', $payload);

        $response->assertStatus(409)
            ->assertJson([
                'status' => 'duplicate',
            ]);
    }

    public function test_github_webhook_with_valid_hmac_succeeds(): void
    {
        $secret = 'github_secret_key_456';
        Config::set('services.github.webhook_secret', $secret);

        $payload = json_encode(['action' => 'opened', 'issue' => ['number' => 42]]);
        $signature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/github',
            [],
            [],
            [],
            [
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
                'HTTP_X_GITHUB_DELIVERY' => 'gh_evt_777',
                'HTTP_X_GITHUB_EVENT' => 'issues.opened',
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(202)
            ->assertJson([
                'status' => 'success',
                'event_id' => 'gh_evt_777',
                'provider' => 'github',
            ]);
    }

    public function test_duplicate_webhook_burst_delivery_prevention(): void
    {
        $mockService = $this->createMock(RedisIdempotencyService::class);
        $mockService->method('isProcessed')->willReturnOnConsecutiveCalls(false, true);
        $mockService->method('acquireLock')->willReturnOnConsecutiveCalls(true, false);
        $this->app->instance(RedisIdempotencyService::class, $mockService);

        $payload = [
            'event_id' => 'evt_burst_999',
            'event_type' => 'payment.authorized',
            'payload' => ['amount' => 5000],
        ];

        // First rapid request -> Accepted 202
        $response1 = $this->postJson('/api/v1/webhooks/generic', $payload);
        $response1->assertStatus(202);

        // Immediate consecutive request with identical event_id -> 409 Conflict
        $response2 = $this->postJson('/api/v1/webhooks/generic', $payload);
        $response2->assertStatus(409)
            ->assertJson([
                'status' => 'duplicate',
                'event_id' => 'evt_burst_999',
            ]);

        $this->assertDatabaseCount('webhook_events', 1);
    }
}
