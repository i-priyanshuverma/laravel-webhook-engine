<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_healthy_status(): void
    {
        Redis::shouldReceive('ping')->once()->andReturn('PONG');

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => [
                    'database' => ['ok', 'latency_ms', 'error'],
                    'redis' => ['ok', 'latency_ms', 'error'],
                ],
                'metrics' => [
                    'total_events',
                    'pending',
                    'completed',
                    'failed',
                    'dlq_unresolved',
                    'avg_latency_ms',
                ],
            ])
            ->assertJson([
                'status' => 'healthy',
                'checks' => [
                    'database' => ['ok' => true],
                    'redis' => ['ok' => true],
                ],
            ]);
    }

    public function test_health_endpoint_returns_zero_metrics_on_empty_database(): void
    {
        Redis::shouldReceive('ping')->once()->andReturn('PONG');

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'metrics' => [
                    'total_events' => 0,
                    'pending' => 0,
                    'completed' => 0,
                    'failed' => 0,
                    'dlq_unresolved' => 0,
                    'avg_latency_ms' => 0,
                ],
            ]);
    }

    public function test_health_endpoint_returns_degraded_when_redis_is_down(): void
    {
        Redis::shouldReceive('ping')->once()->andThrow(new \RuntimeException('Connection refused'));

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'degraded',
                'checks' => [
                    'redis' => ['ok' => false],
                ],
            ]);
    }
}
