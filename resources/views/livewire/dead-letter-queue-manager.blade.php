<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Dead-Letter Queue (DLQ) Manager</h1>
            <p class="text-sm text-slate-400 mt-1">Inspect permanently failed job execution stack traces and perform manual re-dispatch.</p>
        </div>
        <div>
            <button wire:click="replayAll" wire:confirm="Are you sure you want to replay all unresolved DLQ events?" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm rounded-lg shadow-lg shadow-indigo-500/20 transition flex items-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Replay All Unresolved</span>
            </button>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center justify-between">
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <!-- Controls / Filters -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 flex flex-col sm:flex-row gap-4 justify-between">
        <div class="flex items-center space-x-4">
            <div>
                <label class="text-xs font-semibold text-slate-400 uppercase">Status</label>
                <select wire:model.live="statusFilter" class="mt-1 block bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-sm text-slate-200 focus:outline-none focus:border-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="unresolved">Unresolved</option>
                    <option value="replayed">Replayed</option>
                    <option value="ignored">Ignored</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-semibold text-slate-400 uppercase">Provider</label>
                <select wire:model.live="providerFilter" class="mt-1 block bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-sm text-slate-200 focus:outline-none focus:border-indigo-500">
                    <option value="">All Providers</option>
                    <option value="stripe">Stripe</option>
                    <option value="shopify">Shopify</option>
                    <option value="generic">Generic</option>
                </select>
            </div>
        </div>
    </div>

    <!-- DLQ Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase text-xs font-semibold border-b border-slate-800">
                    <tr>
                        <th class="px-6 py-3">ID / Provider</th>
                        <th class="px-6 py-3">Event Type</th>
                        <th class="px-6 py-3">Exception Message</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Failed At</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($dlqEvents as $event)
                        <tr class="hover:bg-slate-800/50 transition">
                            <td class="px-6 py-4 font-medium text-white">
                                <div>#{{ $event->id }}</div>
                                <span class="text-xs text-slate-400 uppercase font-semibold">{{ $event->provider }}</span>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-indigo-400">{{ $event->event_type }}</td>
                            <td class="px-6 py-4 max-w-xs truncate text-rose-400 font-mono text-xs" title="{{ $event->exception_message }}">
                                {{ $event->exception_message }}
                            </td>
                            <td class="px-6 py-4">
                                @if($event->status === 'unresolved')
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20">Unresolved</span>
                                @elseif($event->status === 'replayed')
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Replayed</span>
                                @else
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-500/10 text-slate-400 border border-slate-500/20">Ignored</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-400">{{ $event->failed_at?->diffForHumans() }}</td>
                            <td class="px-6 py-4 text-right space-x-2">
                                @if($event->status === 'unresolved')
                                    <button wire:click="replay({{ $event->id }})" class="px-3 py-1 bg-indigo-600/20 text-indigo-400 hover:bg-indigo-600/30 border border-indigo-500/30 rounded text-xs font-medium transition">Replay</button>
                                    <button wire:click="ignore({{ $event->id }})" class="px-3 py-1 bg-slate-800 text-slate-400 hover:bg-slate-700 rounded text-xs font-medium transition">Ignore</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                No dead-letter queue events found matching current criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-800">
            {{ $dlqEvents->links() }}
        </div>
    </div>
</div>
