<div wire:poll.5s class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Real-Time Queue & Webhook Metrics</h1>
            <p class="text-sm text-slate-400 mt-1">Live monitoring of ingestion throughput, queue status, and latency.</p>
        </div>
        <div class="flex items-center space-x-2 bg-slate-900 border border-slate-800 px-3 py-1.5 rounded-lg text-xs text-slate-400">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Live Polling (5s)</span>
        </div>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Ingested -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Webhooks</span>
                <div class="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <span class="text-3xl font-extrabold text-white tracking-tight">{{ number_format($totalEvents) }}</span>
            </div>
        </div>

        <!-- Pending / Queue Depth -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Queue Pending</span>
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <span class="text-3xl font-extrabold text-amber-400 tracking-tight">{{ number_format($pendingEvents + $processingEvents) }}</span>
            </div>
        </div>

        <!-- Completed Success -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Processed Success</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <span class="text-3xl font-extrabold text-emerald-400 tracking-tight">{{ number_format($completedEvents) }}</span>
            </div>
        </div>

        <!-- DLQ Failures -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">DLQ Unresolved</span>
                <div class="w-8 h-8 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <span class="text-3xl font-extrabold text-rose-400 tracking-tight">{{ number_format($dlqCount) }}</span>
            </div>
        </div>
    </div>

    <!-- Analytics Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Latency & Average Performance -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h3 class="text-base font-semibold text-white">Execution Latency</h3>
            <p class="text-xs text-slate-400 mt-1">Average HTTP request processing duration</p>
            <div class="mt-6 flex items-baseline space-x-2">
                <span class="text-4xl font-extrabold text-indigo-400">{{ $avgLatency }}</span>
                <span class="text-sm font-medium text-slate-400">ms</span>
            </div>
            <div class="mt-4 w-full bg-slate-800 h-2 rounded-full overflow-hidden">
                <div class="bg-indigo-500 h-full rounded-full" style="width: {{ min(100, max(10, $avgLatency)) }}%"></div>
            </div>
        </div>

        <!-- Provider Breakdown -->
        <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h3 class="text-base font-semibold text-white">Provider Ingestion Distribution</h3>
            <p class="text-xs text-slate-400 mt-1">Volume by third-party webhook provider</p>
            <div class="mt-4 grid grid-cols-3 gap-4">
                @foreach(['stripe', 'shopify', 'generic'] as $provider)
                    <div class="bg-slate-950 border border-slate-800 rounded-lg p-4">
                        <span class="text-xs uppercase font-bold text-slate-400">{{ $provider }}</span>
                        <div class="text-2xl font-bold text-white mt-1">
                            {{ number_format($providerBreakdown[$provider] ?? 0) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
