<?php

use App\Http\Controllers\Api\WebhookIngestionController;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/webhooks/{provider}', [WebhookIngestionController::class, 'ingest'])
        ->middleware(VerifyWebhookSignature::class)
        ->name('api.v1.webhooks.ingest');
});
