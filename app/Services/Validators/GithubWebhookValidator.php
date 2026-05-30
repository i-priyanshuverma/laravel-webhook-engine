<?php

namespace App\Services\Validators;

use App\Contracts\WebhookValidatorInterface;
use App\DTOs\WebhookPayloadDTO;

class GithubWebhookValidator implements WebhookValidatorInterface
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

        if (str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }

        $expectedHmac = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($expectedHmac, $signature);
    }
}
