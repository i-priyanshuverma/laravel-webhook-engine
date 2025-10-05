<?php

namespace App\Contracts;

use App\DTOs\WebhookPayloadDTO;

interface WebhookValidatorInterface
{
    /**
     * Validate the webhook payload DTO structure and rules.
     */
    public function validate(WebhookPayloadDTO $dto): bool;

    /**
     * Verify HMAC signature for the given payload.
     */
    public function verifySignature(string $rawPayload, string $signature, string $secret): bool;
}
