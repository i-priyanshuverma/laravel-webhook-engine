<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookJob;
use App\Models\WebhookEvent;
use App\Models\WebhookLog;
use App\Services\RedisIdempotencyService;
use App\Services\Validators\GenericWebhookValidator;
use App\Services\Validators\GithubWebhookValidator;
use App\Services\Validators\ShopifyWebhookValidator;
use App\Services\Validators\StripeWebhookValidator;
use App\Services\WebhookDtoParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class WebhookIngestionController extends Controller
{
    public function __construct(
        private readonly RedisIdempotencyService $idempotencyService,
        private readonly WebhookDtoParserService $dtoParserService,
    ) {}

    public function ingest(Request $request, string $provider): JsonResponse
    {
        $startTime = microtime(true);
        $dto = $this->dtoParserService->parseRequest($request, $provider);

        $validator = match ($dto->provider) {
            'stripe' => new StripeWebhookValidator,
            'shopify' => new ShopifyWebhookValidator,
            'github' => new GithubWebhookValidator,
            default => new GenericWebhookValidator,
        };

        if (! $validator->validate($dto)) {
            $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);
            WebhookLog::create([
                'provider' => $dto->provider,
                'event_id' => $dto->eventId,
                'http_method' => $request->method(),
                'headers' => $request->headers->all(),
                'payload' => $dto->payload,
                'ip_address' => $request->ip(),
                'response_code' => 422,
                'execution_time_ms' => $executionTimeMs,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid webhook payload structure for provider: '.$dto->provider,
            ], 422);
        }

        // Check Redis Idempotency
        if ($this->idempotencyService->isProcessed($dto->provider, $dto->eventId) || ! $this->idempotencyService->acquireLock($dto->provider, $dto->eventId)) {
            $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);
            WebhookLog::create([
                'provider' => $dto->provider,
                'event_id' => $dto->eventId,
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
                'event_id' => $dto->eventId,
                'provider' => $dto->provider,
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

            // Dispatch to Horizon Queue
            ProcessWebhookJob::dispatch($webhookEvent);

            $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

            WebhookLog::create([
                'webhook_event_id' => $webhookEvent->id,
                'provider' => $dto->provider,
                'event_id' => $dto->eventId,
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
            $this->idempotencyService->releaseLock($dto->provider, $dto->eventId);
            $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

            WebhookLog::create([
                'provider' => $dto->provider,
                'event_id' => $dto->eventId,
                'http_method' => $request->method(),
                'headers' => $request->headers->all(),
                'payload' => $dto->payload,
                'ip_address' => $request->ip(),
                'response_code' => 500,
                'execution_time_ms' => $executionTimeMs,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to persist webhook event: '.$e->getMessage(),
            ], 500);
        }
    }
}
