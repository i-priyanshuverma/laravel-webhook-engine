<?php

use App\Livewire\DashboardMetrics;
use App\Livewire\DeadLetterQueueManager;
use App\Livewire\WebhookLogTable;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin/dashboard');
});

Route::get('/admin/dashboard', DashboardMetrics::class)->name('admin.dashboard');
Route::get('/admin/dlq', DeadLetterQueueManager::class)->name('admin.dlq');
Route::get('/admin/logs', WebhookLogTable::class)->name('admin.logs');
