<?php

namespace App\Services\Validators;

use App\Contracts\WebhookValidatorInterface;
use App\DTOs\WebhookPayloadDTO;

class StripeWebhookValidator implements WebhookValidatorInterface
{
    public function validate(WebhookPayloadDTO $dto): bool
    {
        return ! empty($dto->eventId) && ! empty($dto->eventType) && ! empty($dto->payload);
    }

    public function verifySignature(string $rawPayload, string $signature, string $secret): bool
    {
        if (empty($signature) || empty($secret)) {
            return false;
        }

        $items = explode(',', $signature);
        $timestamp = null;
        $v1Signature = null;

        foreach ($items as $item) {
            $parts = explode('=', trim($item), 2);
            if (count($parts) === 2) {
                if ($parts[0] === 't') {
                    $timestamp = $parts[1];
                } elseif ($parts[0] === 'v1') {
                    $v1Signature = $parts[1];
                }
            }
        }

        if (empty($timestamp) || empty($v1Signature)) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $rawPayload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expectedSignature, $v1Signature);
    }
}
