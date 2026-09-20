<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Per-provider circuit breaker settings to prevent cascading failures
    | when a webhook provider's processing consistently fails.
    |
    */

    'circuit_breaker' => [

        // Number of failures within the sliding window to trip the circuit open.
        'failure_threshold' => (int) env('CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),

        // Sliding window duration in seconds for counting failures.
        'window_seconds' => (int) env('CIRCUIT_BREAKER_WINDOW_SECONDS', 60),

        // How long the circuit stays open before transitioning to half-open (seconds).
        'recovery_timeout' => (int) env('CIRCUIT_BREAKER_RECOVERY_TIMEOUT', 120),

        // Consecutive successes in half-open state required to close the circuit.
        'half_open_success_threshold' => (int) env('CIRCUIT_BREAKER_HALF_OPEN_SUCCESS_THRESHOLD', 2),

    ],

];
