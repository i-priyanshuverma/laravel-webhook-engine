<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed circuit breaker implementing CLOSED → OPEN → HALF_OPEN → CLOSED state machine.
 *
 * Tracks per-provider failure rates in a sliding window and automatically opens the circuit
 * when failures exceed a configurable threshold, preventing cascading failures.
 */
class CircuitBreakerService
{
    public const STATE_CLOSED = 'closed';

    public const STATE_OPEN = 'open';

    public const STATE_HALF_OPEN = 'half_open';

    /** Number of failures within the window required to trip the circuit. */
    private int $failureThreshold;

    /** Sliding window duration in seconds for counting failures. */
    private int $windowSeconds;

    /** How long the circuit stays open before transitioning to half-open (seconds). */
    private int $recoveryTimeout;

    /** Number of consecutive successes in half-open state to fully close the circuit. */
    private int $halfOpenSuccessThreshold;

    public function __construct()
    {
        $this->failureThreshold = (int) config('webhook-engine.circuit_breaker.failure_threshold', 5);
        $this->windowSeconds = (int) config('webhook-engine.circuit_breaker.window_seconds', 60);
        $this->recoveryTimeout = (int) config('webhook-engine.circuit_breaker.recovery_timeout', 120);
        $this->halfOpenSuccessThreshold = (int) config('webhook-engine.circuit_breaker.half_open_success_threshold', 2);
    }

    /**
     * Check whether processing is allowed for the given provider.
     */
    public function isAvailable(string $provider): bool
    {
        $state = $this->getState($provider);

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            $openedAt = (int) Redis::get($this->key($provider, 'opened_at'));
            if ((time() - $openedAt) >= $this->recoveryTimeout) {
                $this->transitionTo($provider, self::STATE_HALF_OPEN);

                return true;
            }

            return false;
        }

        // HALF_OPEN — allow limited traffic through for probing
        return true;
    }

    /**
     * Record a successful processing result for the provider.
     */
    public function recordSuccess(string $provider): void
    {
        $state = $this->getState($provider);

        if ($state === self::STATE_HALF_OPEN) {
            $successes = (int) Redis::incr($this->key($provider, 'half_open_successes'));

            if ($successes >= $this->halfOpenSuccessThreshold) {
                $this->transitionTo($provider, self::STATE_CLOSED);
                Log::info("[CircuitBreaker] Circuit CLOSED for provider {$provider} after recovery.");
            }
        }
    }

    /**
     * Record a processing failure for the provider.
     */
    public function recordFailure(string $provider): void
    {
        $state = $this->getState($provider);

        if ($state === self::STATE_HALF_OPEN) {
            $this->transitionTo($provider, self::STATE_OPEN);
            Log::warning("[CircuitBreaker] Circuit re-OPENED for provider {$provider} — failure during half-open probe.");

            return;
        }

        $failureKey = $this->key($provider, 'failures');

        /** @var int $failures */
        $failures = Redis::incr($failureKey);
        if ($failures === 1) {
            Redis::expire($failureKey, $this->windowSeconds);
        }

        if ($failures >= $this->failureThreshold) {
            $this->transitionTo($provider, self::STATE_OPEN);
            Log::error("[CircuitBreaker] Circuit OPENED for provider {$provider} — {$failures} failures in {$this->windowSeconds}s window.");
        }
    }

    /**
     * Get current circuit state for a provider.
     */
    public function getState(string $provider): string
    {
        /** @var string|null $state */
        $state = Redis::get($this->key($provider, 'state'));

        return $state ?: self::STATE_CLOSED;
    }

    /**
     * Force-reset the circuit to closed state (e.g. from admin dashboard).
     */
    public function reset(string $provider): void
    {
        Redis::del([
            $this->key($provider, 'state'),
            $this->key($provider, 'failures'),
            $this->key($provider, 'opened_at'),
            $this->key($provider, 'half_open_successes'),
        ]);

        Log::info("[CircuitBreaker] Circuit manually RESET for provider {$provider}.");
    }

    private function transitionTo(string $provider, string $newState): void
    {
        Redis::set($this->key($provider, 'state'), $newState);

        if ($newState === self::STATE_OPEN) {
            Redis::set($this->key($provider, 'opened_at'), (string) time());
            Redis::del([$this->key($provider, 'half_open_successes')]);
        }

        if ($newState === self::STATE_HALF_OPEN) {
            Redis::set($this->key($provider, 'half_open_successes'), '0');
        }

        if ($newState === self::STATE_CLOSED) {
            Redis::del([
                $this->key($provider, 'failures'),
                $this->key($provider, 'opened_at'),
                $this->key($provider, 'half_open_successes'),
            ]);
        }
    }

    private function key(string $provider, string $suffix): string
    {
        return "circuit_breaker:{$provider}:{$suffix}";
    }
}
