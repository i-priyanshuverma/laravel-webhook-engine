<?php

namespace App\Http\Controllers\Api;

use App\DTOs\WebhookPayloadDTO;
use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Models\WebhookLog;
use App\Services\RedisIdempotencyService;
use App\Services\Validators\GenericWebhookValidator;
use App\Services\Validators\ShopifyWebhookValidator;
use App\Services\Validators\StripeWebhookValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class WebhookIngestionController extends Controller
{
    public function __construct(
        private readonly RedisIdempotencyService $idempotencyService
    ) {}

    public function ingest(Request $request, string $provider): JsonResponse
    {
        $startTime = microtime(true);
        $provider = strtolower($provider);
        $rawPayload = $request->getContent();
        $jsonData = json_decode($rawPayload, true) ?: $request->all();

        $eventId = $this->extractEventId($provider, $jsonData, $request);
        $eventType = $this->extractEventType($provider, $jsonData, $request);

        $dto = new WebhookPayloadDTO(
            eventId: $eventId,
            provider: $provider,
            eventType: $eventType,
            payload: is_array($jsonData) ? $jsonData : ['raw' => $rawPayload],
            headers: $request->headers->all(),
            rawPayload: $rawPayload,
            signature: $this->extractSignature($provider, $request),
        );

        $validator = match ($provider) {
            'stripe' => new StripeWebhookValidator(),
            'shopify' => new ShopifyWebhookValidator(),
            default => new GenericWebhookValidator(),
        };

        if (! $validator->validate($dto)) {
            $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);
            WebhookLog::create([
                'provider' => $provider,
                'event_id' => $eventId,
                'http_method' => $request->method(),
                'headers' => $request->headers->all(),
                'payload' => $dto->payload,
                'ip_address' => $request->ip(),
                'response_code' => 422,
                'execution_time_ms' => $executionTimeMs,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid webhook payload structure for provider: ' . $provider,
            ], 422);
        }

        // Check Redis Idempotency
        if ($this->idempotencyService->isProcessed($provider, $eventId) || ! $this->idempotencyService->acquireLock($provider, $eventId)) {
            $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);
            WebhookLog::create([
                'provider' => $provider,
                'event_id' => $eventId,
                'http_method' => $request->method(),
                'headers' => $request->headers->all(),
                'payload' => $dto->payload,
                'ip_address' => $request->ip(),
                'response_code' => 409,
                'execution_time_ms' => $executionTimeMs,
            ]);

            return response()->json([
                'status' => 'duplicate',
                'message' => 'Duplicate webhook event received and ignored',
                'event_id' => $eventId,
                'provider' => $provider,
            ], 409);
        }

        try {
            $webhookEvent = WebhookEvent::updateOrCreate(
                [
                    'event_id' => $dto->eventId,
                    'provider' => $dto->provider,
                ],
                [
                    'event_type' => $dto->eventType,
                    'payload' => $dto->payload,
                    'status' => WebhookEvent::STATUS_PENDING,
                ]
            );

            $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

            WebhookLog::create([
                'webhook_event_id' => $webhookEvent->id,
                'provider' => $provider,
                'event_id' => $eventId,
                'http_method' => $request->method(),
                'headers' => $request->headers->all(),
                'payload' => $dto->payload,
                'ip_address' => $request->ip(),
                'response_code' => 202,
                'execution_time_ms' => $executionTimeMs,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook received and queued for processing',
                'event_id' => $webhookEvent->event_id,
                'provider' => $webhookEvent->provider,
            ], 202);
        } catch (Throwable $e) {
            $this->idempotencyService->releaseLock($provider, $eventId);
            $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

            WebhookLog::create([
                'provider' => $provider,
                'event_id' => $eventId,
                'http_method' => $request->method(),
                'headers' => $request->headers->all(),
                'payload' => $dto->payload,
                'ip_address' => $request->ip(),
                'response_code' => 500,
                'execution_time_ms' => $executionTimeMs,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to persist webhook event: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractEventId(string $provider, array $data, Request $request): string
    {
        return match ($provider) {
            'stripe' => (string) ($data['id'] ?? 'evt_' . bin2hex(random_bytes(8))),
            'shopify' => (string) ($request->header('X-Shopify-Webhook-Id') ?? $data['id'] ?? 'shp_' . bin2hex(random_bytes(8))),
            default => (string) ($data['event_id'] ?? $data['id'] ?? 'gen_' . bin2hex(random_bytes(8))),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractEventType(string $provider, array $data, Request $request): string
    {
        return match ($provider) {
            'stripe' => (string) ($data['type'] ?? 'unknown'),
            'shopify' => (string) ($request->header('X-Shopify-Topic') ?? $data['topic'] ?? 'unknown'),
            default => (string) ($data['event_type'] ?? $data['type'] ?? 'generic.event'),
        };
    }

    private function extractSignature(string $provider, Request $request): ?string
    {
        return match ($provider) {
            'stripe' => $request->header('Stripe-Signature'),
            'shopify' => $request->header('X-Shopify-Hmac-SHA256'),
            default => $request->header('X-Signature') ?? $request->header('X-Hub-Signature-256'),
        };
    }
}
