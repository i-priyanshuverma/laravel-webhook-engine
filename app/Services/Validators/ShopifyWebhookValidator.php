<?php

namespace App\Services\Validators;

use App\Contracts\WebhookValidatorInterface;
use App\DTOs\WebhookPayloadDTO;

class ShopifyWebhookValidator implements WebhookValidatorInterface
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

        $calculatedHmac = base64_encode(hash_hmac('sha256', $rawPayload, $secret, true));

        return hash_equals($calculatedHmac, $signature);
    }
}
