<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Encrypts and decrypts webhook payloads at rest using Laravel's AES-256-CBC encryption (APP_KEY).
 *
 * Sensitive webhook data (payment info, PII, API credentials) is encrypted before database
 * persistence and decrypted transparently on read, ensuring data-at-rest protection.
 */
class PayloadEncryptionService
{
    /**
     * Encrypt a payload array into an AES-256-CBC ciphertext string.
     *
     * @param  array<string, mixed>  $payload
     */
    public function encrypt(array $payload): string
    {
        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Decrypt a ciphertext string back into a payload array.
     *
     * @return array<string, mixed>
     */
    public function decrypt(string $encryptedPayload): array
    {
        try {
            $json = Crypt::decryptString($encryptedPayload);

            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (DecryptException $e) {
            Log::error('[PayloadEncryption] Failed to decrypt payload: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Check whether a string looks like an encrypted payload (base64-encoded JSON envelope).
     */
    public function isEncrypted(string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        $json = json_decode($decoded, true);

        return is_array($json) && isset($json['iv'], $json['value'], $json['mac']);
    }
}
