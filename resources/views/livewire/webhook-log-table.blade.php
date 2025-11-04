<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Webhook HTTP Execution Logs</h1>
        <p class="text-sm text-slate-400 mt-1">Audit log of all incoming HTTP webhook payload requests, response statuses, and latency metrics.</p>
    </div>

    <!-- Search & Filter Controls -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 flex flex-col md:flex-row gap-4 justify-between">
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by Event ID or IP Address..." class="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-2 text-sm text-slate-200 focus:outline-none focus:border-indigo-500 placeholder-slate-500">
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="providerFilter" class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-indigo-500">
                <option value="">All Providers</option>
                <option value="stripe">Stripe</option>
                <option value="shopify">Shopify</option>
                <option value="generic">Generic</option>
            </select>

            <select wire:model.live="responseCodeFilter" class="bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-indigo-500">
                <option value="">All Response Codes</option>
                <option value="202">202 Accepted</option>
                <option value="401">401 Unauthorized</option>
                <option value="409">409 Duplicate</option>
                <option value="422">422 Invalid</option>
                <option value="500">500 Server Error</option>
            </select>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase text-xs font-semibold border-b border-slate-800">
                    <tr>
                        <th class="px-6 py-3">Timestamp</th>
                        <th class="px-6 py-3">Provider</th>
                        <th class="px-6 py-3">Event ID</th>
                        <th class="px-6 py-3">Status Code</th>
                        <th class="px-6 py-3">Latency</th>
                        <th class="px-6 py-3">IP Address</th>
                        <th class="px-6 py-3 text-right">Payload</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-800/50 transition cursor-pointer" wire:click="toggleLogDetails({{ $log->id }})">
                            <td class="px-6 py-4 text-xs text-slate-400 font-mono">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                            <td class="px-6 py-4 uppercase font-bold text-xs text-slate-300">{{ $log->provider }}</td>
                            <td class="px-6 py-4 font-mono text-xs text-indigo-400">{{ $log->event_id ?? 'N/A' }}</td>
                            <td class="px-6 py-4">
                                @if($log->response_code === 202)
                                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">202 Accepted</span>
                                @elseif($log->response_code === 409)
                                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20">409 Conflict</span>
                                @else
                                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20">{{ $log->response_code }} Error</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-300 font-mono">{{ number_format($log->execution_time_ms, 2) }} ms</td>
                            <td class="px-6 py-4 text-xs text-slate-400 font-mono">{{ $log->ip_address }}</td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-xs text-indigo-400 hover:text-indigo-300 underline font-medium">Inspect</button>
                            </td>
                        </tr>
                        @if($selectedLogId === $log->id)
                            <tr class="bg-slate-950">
                                <td colspan="7" class="px-6 py-4 border-t border-b border-slate-800">
                                    <div class="space-y-3">
                                        <div class="text-xs font-bold uppercase text-slate-400">Request Raw Payload & Headers</div>
                                        <pre class="bg-slate-900 p-4 rounded-lg text-xs font-mono text-emerald-400 overflow-x-auto border border-slate-800">{{ json_encode(['headers' => $log->headers, 'payload' => $log->payload], JSON_PRETTY_PRINT) }}</pre>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                No webhook execution logs found matching search filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-800">
            {{ $logs->links() }}
        </div>
    </div>
</div>
