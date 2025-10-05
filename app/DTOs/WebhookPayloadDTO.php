<?php

namespace App\DTOs;

readonly class WebhookPayloadDTO
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public string $eventId,
        public string $provider,
        public string $eventType,
        public array $payload,
        public array $headers = [],
        public ?string $rawPayload = null,
        public ?string $signature = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eventId: (string) ($data['event_id'] ?? $data['id'] ?? md5((string) json_encode($data))),
            provider: strtolower((string) ($data['provider'] ?? 'generic')),
            eventType: (string) ($data['event_type'] ?? $data['type'] ?? 'unknown'),
            payload: (array) ($data['payload'] ?? $data),
            headers: (array) ($data['headers'] ?? []),
            rawPayload: isset($data['raw_payload']) ? (string) $data['raw_payload'] : null,
            signature: isset($data['signature']) ? (string) $data['signature'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'provider' => $this->provider,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
            'headers' => $this->headers,
            'signature' => $this->signature,
        ];
    }
}
