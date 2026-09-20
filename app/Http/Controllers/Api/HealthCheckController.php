<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeadLetterQueueEvent;
use App\Models\WebhookEvent;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthCheckController extends Controller
{
    /**
     * Service health probe for Kubernetes liveness/readiness checks and monitoring dashboards.
     *
     * GET /api/v1/health
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $healthy = ! in_array(false, array_column($checks, 'ok'), true);

        $metrics = $this->gatherMetrics();

        $payload = [
            'status' => $healthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'metrics' => $metrics,
        ];

        return response()->json($payload, $healthy ? 200 : 503);
    }

    /**
     * @return array{ok: bool, latency_ms: float|null, error: string|null}
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return ['ok' => true, 'latency_ms' => $latency, 'error' => null];
        } catch (Throwable $e) {
            return ['ok' => false, 'latency_ms' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, latency_ms: float|null, error: string|null}
     */
    private function checkRedis(): array
    {
        try {
            $start = microtime(true);
            /** @var string $pong */
            $pong = Redis::ping();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return ['ok' => (bool) $pong, 'latency_ms' => $latency, 'error' => null];
        } catch (Throwable $e) {
            return ['ok' => false, 'latency_ms' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{total_events: int, pending: int, completed: int, failed: int, dlq_unresolved: int, avg_latency_ms: float}
     */
    private function gatherMetrics(): array
    {
        return [
            'total_events' => WebhookEvent::count(),
            'pending' => WebhookEvent::where('status', WebhookEvent::STATUS_PENDING)->count(),
            'completed' => WebhookEvent::where('status', WebhookEvent::STATUS_COMPLETED)->count(),
            'failed' => WebhookEvent::where('status', WebhookEvent::STATUS_FAILED)->count(),
            'dlq_unresolved' => DeadLetterQueueEvent::where('status', DeadLetterQueueEvent::STATUS_UNRESOLVED)->count(),
            'avg_latency_ms' => round((float) WebhookLog::avg('execution_time_ms'), 2),
        ];
    }
}
