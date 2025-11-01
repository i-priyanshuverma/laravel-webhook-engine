<?php

namespace App\Livewire;

use App\Models\DeadLetterQueueEvent;
use App\Models\WebhookEvent;
use App\Models\WebhookLog;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DashboardMetrics extends Component
{
    public function render(): View
    {
        $totalEvents = WebhookEvent::count();
        $pendingEvents = WebhookEvent::where('status', WebhookEvent::STATUS_PENDING)->count();
        $processingEvents = WebhookEvent::where('status', WebhookEvent::STATUS_PROCESSING)->count();
        $completedEvents = WebhookEvent::where('status', WebhookEvent::STATUS_COMPLETED)->count();
        $failedEvents = WebhookEvent::where('status', WebhookEvent::STATUS_FAILED)->count();
        $dlqCount = DeadLetterQueueEvent::where('status', DeadLetterQueueEvent::STATUS_UNRESOLVED)->count();
        $avgLatency = round((float) WebhookLog::avg('execution_time_ms'), 2);

        $providerBreakdown = WebhookEvent::selectRaw('provider, count(*) as count')
            ->groupBy('provider')
            ->pluck('count', 'provider')
            ->toArray();

        $recentEvents = WebhookEvent::latest()->take(5)->get();

        return view('livewire.dashboard-metrics', [
            'totalEvents' => $totalEvents,
            'pendingEvents' => $pendingEvents,
            'processingEvents' => $processingEvents,
            'completedEvents' => $completedEvents,
            'failedEvents' => $failedEvents,
            'dlqCount' => $dlqCount,
            'avgLatency' => $avgLatency,
            'providerBreakdown' => $providerBreakdown,
            'recentEvents' => $recentEvents,
        ])->layout('components.layouts.app', ['title' => 'Dashboard Metrics - Webhook Engine']);
    }
}
