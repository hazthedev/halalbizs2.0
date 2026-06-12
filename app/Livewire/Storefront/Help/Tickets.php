<?php

namespace App\Livewire\Storefront\Help;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Buyer support tickets — list, new-ticket form, and thread view.
 * Every query goes through ownTickets(): users only ever see their own.
 */
#[Layout('layouts.storefront')]
class Tickets extends Component
{
    #[Url(as: 'ticket')]
    public ?int $selectedId = null;

    public bool $showForm = false;

    public string $subject = '';

    public string $body = '';

    public string $replyBody = '';

    public function mount(): void
    {
        if ($this->selectedId !== null) {
            $this->ownTickets()->findOrFail($this->selectedId); // 404 on foreign deep links
        }
    }

    public function startTicket(): void
    {
        $this->reset(['subject', 'body', 'selectedId']);
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->reset(['showForm', 'subject', 'body']);
        $this->resetErrorBag();
    }

    public function create(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'min:5', 'max:120'],
            'body' => ['required', 'string', 'min:20', 'max:3000'],
        ], attributes: [
            'subject' => __('subject'),
            'body' => __('message'),
        ]);

        $ticket = auth()->user()->tickets()->create([
            'subject' => strip_tags(trim($this->subject)),
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Normal,
        ]);

        $ticket->replies()->create([
            'author_type' => 'user',
            'author_id' => auth()->id(),
            'body' => strip_tags(trim($this->body)),
        ]);

        $this->reset(['showForm', 'subject', 'body']);
        $this->selectedId = $ticket->id;
        $this->dispatch('toast', message: __('Ticket created — support usually replies within one working day.'));
    }

    public function select(int $ticketId): void
    {
        $ticket = $this->ownTickets()->findOrFail($ticketId);

        $this->selectedId = $ticket->id;
        $this->showForm = false;
        $this->replyBody = '';
        $this->resetErrorBag();
    }

    public function backToList(): void
    {
        $this->reset(['selectedId', 'replyBody']);
        $this->resetErrorBag();
    }

    public function reply(): void
    {
        $ticket = $this->ownTickets()->findOrFail($this->selectedId);

        if ($ticket->isClosed()) {
            $this->dispatch('toast', message: __('This ticket is closed — start a new one if you still need help.'), type: 'error');

            return;
        }

        $this->validate([
            'replyBody' => ['required', 'string', 'min:5', 'max:3000'],
        ], attributes: [
            'replyBody' => __('message'),
        ]);

        $ticket->replies()->create([
            'author_type' => 'user',
            'author_id' => auth()->id(),
            'body' => strip_tags(trim($this->replyBody)),
        ]);

        // A user reply puts the ticket back in the support queue.
        $ticket->update(['status' => TicketStatus::Open]);

        $this->replyBody = '';
        $this->dispatch('toast', message: __('Reply sent'));
    }

    public function close(): void
    {
        $ticket = $this->ownTickets()->findOrFail($this->selectedId);

        $ticket->update(['status' => TicketStatus::Closed]);

        $this->dispatch('toast', message: __('Ticket closed'));
    }

    public function render()
    {
        $selected = $this->selectedId !== null
            ? $this->ownTickets()->with('replies')->find($this->selectedId)
            : null;

        if ($this->selectedId !== null && $selected === null) {
            $this->reset(['selectedId', 'replyBody']);
        }

        return view('livewire.storefront.help.tickets', [
            'tickets' => $this->ownTickets()->withCount('replies')->latest('updated_at')->get(),
            'selected' => $selected,
        ])->title(__('Support tickets'));
    }

    private function ownTickets(): HasMany
    {
        return auth()->user()->tickets();
    }
}
