<?php

namespace Tests\Unit;

use App\Services\PayloadEncryptionService;
use Tests\TestCase;

class PayloadEncryptionServiceTest extends TestCase
{
    private PayloadEncryptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PayloadEncryptionService;
    }

    public function test_encrypt_decrypt_round_trip_preserves_payload(): void
    {
        $payload = [
            'id' => 'evt_charge_001',
            'type' => 'charge.succeeded',
            'data' => [
                'object' => [
                    'amount' => 9900,
                    'currency' => 'usd',
                    'customer' => 'cus_secret_123',
                ],
            ],
        ];

        $encrypted = $this->service->encrypt($payload);

        // Encrypted output should not contain the original plaintext
        $this->assertStringNotContainsString('cus_secret_123', $encrypted);
        $this->assertStringNotContainsString('charge.succeeded', $encrypted);

        $decrypted = $this->service->decrypt($encrypted);

        $this->assertEquals($payload, $decrypted);
    }

    public function test_encrypt_produces_different_ciphertext_each_time(): void
    {
        $payload = ['key' => 'value'];

        $encrypted1 = $this->service->encrypt($payload);
        $encrypted2 = $this->service->encrypt($payload);

        // AES-256-CBC uses a random IV, so ciphertexts should differ
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to the same payload
        $this->assertEquals($this->service->decrypt($encrypted1), $this->service->decrypt($encrypted2));
    }

    public function test_decrypt_returns_empty_array_for_invalid_ciphertext(): void
    {
        $result = $this->service->decrypt('this-is-not-valid-ciphertext');

        $this->assertEquals([], $result);
    }

    public function test_is_encrypted_detects_encrypted_strings(): void
    {
        $payload = ['amount' => 5000];
        $encrypted = $this->service->encrypt($payload);

        $this->assertTrue($this->service->isEncrypted($encrypted));
    }

    public function test_is_encrypted_rejects_plaintext(): void
    {
        $this->assertFalse($this->service->isEncrypted('just a regular string'));
        $this->assertFalse($this->service->isEncrypted(''));
        $this->assertFalse($this->service->isEncrypted('{"key":"value"}'));
    }
}
