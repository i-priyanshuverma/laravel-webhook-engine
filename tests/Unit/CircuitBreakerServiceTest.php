<?php

namespace Tests\Unit;

use App\Services\CircuitBreakerService;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CircuitBreakerServiceTest extends TestCase
{
    private CircuitBreakerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CircuitBreakerService;
    }

    public function test_circuit_starts_in_closed_state(): void
    {
        Redis::shouldReceive('get')
            ->with('circuit_breaker:stripe:state')
            ->once()
            ->andReturn(null);

        $this->assertEquals(CircuitBreakerService::STATE_CLOSED, $this->service->getState('stripe'));
    }

    public function test_circuit_is_available_when_closed(): void
    {
        Redis::shouldReceive('get')
            ->with('circuit_breaker:stripe:state')
            ->once()
            ->andReturn(null);

        $this->assertTrue($this->service->isAvailable('stripe'));
    }

    public function test_circuit_opens_after_failure_threshold(): void
    {
        // First call: getState check inside recordFailure
        Redis::shouldReceive('get')
            ->with('circuit_breaker:stripe:state')
            ->once()
            ->andReturn(CircuitBreakerService::STATE_CLOSED);

        // incr failures counter
        Redis::shouldReceive('incr')
            ->with('circuit_breaker:stripe:failures')
            ->once()
            ->andReturn(5);

        // Transition to OPEN: set state, set opened_at, del half_open_successes
        Redis::shouldReceive('set')
            ->with('circuit_breaker:stripe:state', CircuitBreakerService::STATE_OPEN)
            ->once();

        Redis::shouldReceive('set')
            ->with('circuit_breaker:stripe:opened_at', \Mockery::type('string'))
            ->once();

        Redis::shouldReceive('del')
            ->with(['circuit_breaker:stripe:half_open_successes'])
            ->once();

        $this->service->recordFailure('stripe');
    }

    public function test_circuit_blocks_when_open_and_recovery_not_elapsed(): void
    {
        Redis::shouldReceive('get')
            ->with('circuit_breaker:stripe:state')
            ->once()
            ->andReturn(CircuitBreakerService::STATE_OPEN);

        // opened_at is very recent
        Redis::shouldReceive('get')
            ->with('circuit_breaker:stripe:opened_at')
            ->once()
            ->andReturn((string) time());

        $this->assertFalse($this->service->isAvailable('stripe'));
    }

    public function test_circuit_transitions_to_half_open_after_recovery_timeout(): void
    {
        Redis::shouldReceive('get')
            ->with('circuit_breaker:stripe:state')
            ->once()
            ->andReturn(CircuitBreakerService::STATE_OPEN);

        // opened_at is well past recovery timeout
        Redis::shouldReceive('get')
            ->with('circuit_breaker:stripe:opened_at')
            ->once()
            ->andReturn((string) (time() - 300));

        // Transition to HALF_OPEN
        Redis::shouldReceive('set')
            ->with('circuit_breaker:stripe:state', CircuitBreakerService::STATE_HALF_OPEN)
            ->once();

        Redis::shouldReceive('set')
            ->with('circuit_breaker:stripe:half_open_successes', '0')
            ->once();

        $this->assertTrue($this->service->isAvailable('stripe'));
    }

    public function test_circuit_closes_after_half_open_success_threshold(): void
    {
        // getState returns half_open
        Redis::shouldReceive('get')
            ->with('circuit_breaker:stripe:state')
            ->once()
            ->andReturn(CircuitBreakerService::STATE_HALF_OPEN);

        // incr half_open_successes hits threshold
        Redis::shouldReceive('incr')
            ->with('circuit_breaker:stripe:half_open_successes')
            ->once()
            ->andReturn(2);

        // Transition to CLOSED: set state, del keys
        Redis::shouldReceive('set')
            ->with('circuit_breaker:stripe:state', CircuitBreakerService::STATE_CLOSED)
            ->once();

        Redis::shouldReceive('del')
            ->with([
                'circuit_breaker:stripe:failures',
                'circuit_breaker:stripe:opened_at',
                'circuit_breaker:stripe:half_open_successes',
            ])
            ->once();

        $this->service->recordSuccess('stripe');
    }

    public function test_reset_clears_all_circuit_keys(): void
    {
        Redis::shouldReceive('del')
            ->with([
                'circuit_breaker:stripe:state',
                'circuit_breaker:stripe:failures',
                'circuit_breaker:stripe:opened_at',
                'circuit_breaker:stripe:half_open_successes',
            ])
            ->once();

        $this->service->reset('stripe');
    }
}
