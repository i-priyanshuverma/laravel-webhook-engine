<?php

namespace App\Http\Middleware;

use App\Services\Validators\ShopifyWebhookValidator;
use App\Services\Validators\StripeWebhookValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $provider = strtolower((string) $request->route('provider'));
        $rawPayload = $request->getContent();

        $secret = match ($provider) {
            'stripe' => config('services.stripe.webhook_secret'),
            'shopify' => config('services.shopify.webhook_secret'),
            default => config("services.{$provider}.webhook_secret"),
        };

        if (empty($secret)) {
            return $next($request);
        }

        $signature = match ($provider) {
            'stripe' => $request->header('Stripe-Signature'),
            'shopify' => $request->header('X-Shopify-Hmac-SHA256'),
            default => $request->header('X-Signature') ?? $request->header('X-Hub-Signature-256'),
        };

        if (empty($signature)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing HMAC signature header for provider: ' . $provider,
            ], 401);
        }

        $validator = match ($provider) {
            'stripe' => new StripeWebhookValidator(),
            'shopify' => new ShopifyWebhookValidator(),
            default => null,
        };

        if ($validator && ! $validator->verifySignature($rawPayload, $signature, $secret)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid HMAC signature for provider: ' . $provider,
            ], 401);
        }

        return $next($request);
    }
}
