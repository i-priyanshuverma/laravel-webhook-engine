<?php

use App\Livewire\DashboardMetrics;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin/dashboard');
});

Route::get('/admin/dashboard', DashboardMetrics::class)->name('admin.dashboard');
