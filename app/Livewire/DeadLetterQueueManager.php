<?php

namespace App\Livewire;

use App\Models\DeadLetterQueueEvent;
use App\Services\DeadLetterQueueService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class DeadLetterQueueManager extends Component
{
    use WithPagination;

    public string $statusFilter = 'unresolved';

    public string $providerFilter = '';

    public ?int $selectedDlqId = null;

    public function replay(int $id, DeadLetterQueueService $dlqService): void
    {
        $dlqEvent = DeadLetterQueueEvent::find($id);
        if ($dlqEvent && $dlqService->replayEvent($dlqEvent, 'admin_ui')) {
            session()->flash('message', "Event {$dlqEvent->id} successfully queued for re-processing.");
        }
    }

    public function ignore(int $id, DeadLetterQueueService $dlqService): void
    {
        $dlqEvent = DeadLetterQueueEvent::find($id);
        if ($dlqEvent) {
            $dlqService->ignoreEvent($dlqEvent);
            session()->flash('message', "Event {$dlqEvent->id} has been ignored.");
        }
    }

    public function replayAll(DeadLetterQueueService $dlqService): void
    {
        $count = $dlqService->replayBulkWithExponentialBackoff([], 'admin_ui_bulk');
        session()->flash('message', "Successfully queued {$count} unresolved DLQ events with bulk exponential backoff.");
    }

    public function render(): View
    {
        $query = DeadLetterQueueEvent::with('webhookEvent')->latest();

        if (! empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        if (! empty($this->providerFilter)) {
            $query->where('provider', strtolower($this->providerFilter));
        }

        $dlqEvents = $query->paginate(10);

        return view('livewire.dead-letter-queue-manager', [
            'dlqEvents' => $dlqEvents,
        ])->layout('components.layouts.app', ['title' => 'DLQ Manager - Webhook Engine']);
    }
}
