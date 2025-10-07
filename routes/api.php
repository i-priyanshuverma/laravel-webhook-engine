<?php

use App\Http\Controllers\Api\WebhookIngestionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/webhooks/{provider}', [WebhookIngestionController::class, 'ingest'])
        ->name('api.v1.webhooks.ingest');
});
