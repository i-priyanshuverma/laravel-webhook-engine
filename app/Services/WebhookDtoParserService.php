<?php

namespace App\Services;

use App\DTOs\WebhookPayloadDTO;
use Illuminate\Http\Request;
use JsonException;

class WebhookDtoParserService
{
    /**
     * Fast parsing of incoming HTTP request into a typed WebhookPayloadDTO.
     */
    public function parseRequest(Request $request, string $provider): WebhookPayloadDTO
    {
        $rawPayload = $request->getContent();
        $provider = strtolower($provider);

        try {
            $jsonData = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $jsonData = $request->all();
        }

        if (! is_array($jsonData)) {
            $jsonData = ['raw' => $rawPayload];
        }

        $hashFallback = function (string $input): string {
            return function_exists('hash') && in_array('xxh3', hash_algos(), true)
                ? hash('xxh3', $input)
                : md5($input);
        };

        $eventId = match ($provider) {
            'stripe' => (string) ($jsonData['id'] ?? 'evt_'.$hashFallback($rawPayload)),
            'shopify' => (string) ($request->header('X-Shopify-Webhook-Id') ?? $jsonData['id'] ?? 'shp_'.$hashFallback($rawPayload)),
            default => (string) ($jsonData['event_id'] ?? $jsonData['id'] ?? 'gen_'.$hashFallback($rawPayload)),
        };

        $eventType = match ($provider) {
            'stripe' => (string) ($jsonData['type'] ?? 'unknown'),
            'shopify' => (string) ($request->header('X-Shopify-Topic') ?? $jsonData['topic'] ?? 'unknown'),
            default => (string) ($jsonData['event_type'] ?? $jsonData['type'] ?? 'generic.event'),
        };

        $signature = match ($provider) {
            'stripe' => $request->header('Stripe-Signature'),
            'shopify' => $request->header('X-Shopify-Hmac-SHA256'),
            default => $request->header('X-Signature') ?? $request->header('X-Hub-Signature-256'),
        };

        return new WebhookPayloadDTO(
            eventId: $eventId,
            provider: $provider,
            eventType: $eventType,
            payload: $jsonData,
            headers: $request->headers->all(),
            rawPayload: $rawPayload,
            signature: $signature,
        );
    }
}
