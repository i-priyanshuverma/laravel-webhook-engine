<?php

require __DIR__ . '/../../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

$baseUrl = $argv[1] ?? 'http://127.0.0.1:8000';
$totalRequests = (int) ($argv[2] ?? 100);
$concurrency = (int) ($argv[3] ?? 10);

echo "=====================================================\n";
echo "       LARAVEL WEBHOOK ENGINE STRESS BENCHMARK       \n";
echo "=====================================================\n";
echo "Target Base URL: {$baseUrl}\n";
echo "Total Requests:  {$totalRequests}\n";
echo "Concurrency:     {$concurrency}\n";
echo "-----------------------------------------------------\n\n";

$client = new Client([
    'base_uri' => $baseUrl,
    'timeout' => 5.0,
]);

$successCount = 0;
$duplicateCount = 0;
$errorCount = 0;

$requests = function ($total) {
    $providers = ['stripe', 'shopify', 'generic'];

    for ($i = 1; $i <= $total; $i++) {
        $provider = $providers[array_rand($providers)];
        $eventId = 'stress_' . $provider . '_' . sprintf('%04d', (int) ceil($i / 2));

        $payload = json_encode([
            'id' => $eventId,
            'event_id' => $eventId,
            'type' => 'stress.test.event',
            'timestamp' => time(),
            'data' => ['iteration' => $i, 'random_hash' => bin2hex(random_bytes(16))],
        ]);

        yield new Request(
            'POST',
            "/api/v1/webhooks/{$provider}",
            [
                'Content-Type' => 'application/json',
                'User-Agent' => 'WebhookEngine-StressTest/1.0',
            ],
            $payload ?: '{}'
        );
    }
};

$startTime = microtime(true);

$pool = new Pool($client, $requests($totalRequests), [
    'concurrency' => $concurrency,
    'fulfilled' => function (Response $response, $index) use (&$successCount, &$duplicateCount, &$errorCount) {
        $statusCode = $response->getStatusCode();
        if ($statusCode === 202) {
            $successCount++;
        } elseif ($statusCode === 409) {
            $duplicateCount++;
        } else {
            $errorCount++;
        }
    },
    'rejected' => function ($reason, $index) use (&$errorCount) {
        $errorCount++;
    },
]);

$promise = $pool->promise();
$promise->wait();

$totalTime = microtime(true) - $startTime;
$rps = round($totalRequests / max($totalTime, 0.001), 2);

echo "=====================================================\n";
echo "                  BENCHMARK RESULTS                  \n";
echo "=====================================================\n";
echo "Total Elapsed Time:   " . number_format($totalTime, 3) . " s\n";
echo "Throughput (RPS):     {$rps} req/sec\n";
echo "Successful Ingested:  {$successCount} (202 Accepted)\n";
echo "Duplicate Rejected:   {$duplicateCount} (409 Conflict)\n";
echo "Errors / Failed:      {$errorCount}\n";
echo "Success + Dup Rate:   " . round((($successCount + $duplicateCount) / max($totalRequests, 1)) * 100, 2) . "%\n";
echo "=====================================================\n";
