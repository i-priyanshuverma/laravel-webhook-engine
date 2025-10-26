<?php

namespace Tests\Feature;

use App\Models\WebhookEvent;
use App\Models\WebhookLog;
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
        $signedPayload = $timestamp . '.' . $payload;
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
}
