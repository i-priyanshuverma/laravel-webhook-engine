<?php

namespace App\Livewire;

use App\Models\WebhookLog;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class WebhookLogTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $providerFilter = '';

    public string $responseCodeFilter = '';

    public ?int $selectedLogId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingProviderFilter(): void
    {
        $this->resetPage();
    }

    public function updatingResponseCodeFilter(): void
    {
        $this->resetPage();
    }

    public function toggleLogDetails(int $id): void
    {
        $this->selectedLogId = $this->selectedLogId === $id ? null : $id;
    }

    public function render(): View
    {
        $query = WebhookLog::with('webhookEvent')->latest();

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('event_id', 'like', '%'.$this->search.'%')
                    ->orWhere('ip_address', 'like', '%'.$this->search.'%');
            });
        }

        if (! empty($this->providerFilter)) {
            $query->where('provider', strtolower($this->providerFilter));
        }

        if (! empty($this->responseCodeFilter)) {
            $query->where('response_code', (int) $this->responseCodeFilter);
        }

        $logs = $query->paginate(15);
        $selectedLog = $this->selectedLogId ? WebhookLog::find($this->selectedLogId) : null;

        return view('livewire.webhook-log-table', [
            'logs' => $logs,
            'selectedLog' => $selectedLog,
        ])->layout('components.layouts.app', ['title' => 'Webhook Logs - Webhook Engine']);
    }
}
