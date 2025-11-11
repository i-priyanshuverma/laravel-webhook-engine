<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Throwable;

class RedisIdempotencyService
{
    public const DEFAULT_TTL = 86400; // 24 hours

    /**
     * Acquire atomic Redis lock for a webhook event ID.
     */
    public function acquireLock(string $provider, string $eventId, int $ttl = self::DEFAULT_TTL): bool
    {
        $key = $this->buildKey($provider, $eventId);

        try {
            /** @var bool|string $result */
            $result = Redis::set($key, 'locked', ['ex' => $ttl, 'nx']);

            return (bool) $result;
        } catch (Throwable $e) {
            return true;
        }
    }

    /**
     * Release lock key for a webhook event.
     */
    public function releaseLock(string $provider, string $eventId): void
    {
        $key = $this->buildKey($provider, $eventId);

        try {
            Redis::del($key);
        } catch (Throwable $e) {
            // Ignore
        }
    }

    /**
     * Check if event has already been completed.
     */
    public function isProcessed(string $provider, string $eventId): bool
    {
        $key = $this->buildKey($provider, $eventId).':completed';

        try {
            return (bool) Redis::exists($key);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Mark event as completed in Redis cache.
     */
    public function markProcessed(string $provider, string $eventId, int $ttl = self::DEFAULT_TTL): void
    {
        $key = $this->buildKey($provider, $eventId).':completed';

        try {
            Redis::set($key, 'processed', ['ex' => $ttl]);
        } catch (Throwable $e) {
            // Ignore
        }
    }

    public function buildKey(string $provider, string $eventId): string
    {
        return sprintf('webhook_idempotency:%s:%s', strtolower($provider), $eventId);
    }
}
