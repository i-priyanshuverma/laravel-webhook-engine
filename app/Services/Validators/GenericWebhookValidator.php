<?php

namespace App\Services\Validators;

use App\Contracts\WebhookValidatorInterface;
use App\DTOs\WebhookPayloadDTO;

class GenericWebhookValidator implements WebhookValidatorInterface
{
    public function validate(WebhookPayloadDTO $dto): bool
    {
        return ! empty($dto->eventId) && ! empty($dto->payload);
    }

    public function verifySignature(string $rawPayload, string $signature, string $secret): bool
    {
        if (empty($secret)) {
            return true;
        }

        $calculated = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($calculated, $signature);
    }
}
