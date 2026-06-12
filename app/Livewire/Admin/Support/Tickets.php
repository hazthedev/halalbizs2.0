<?php

namespace App\Livewire\Admin\Support;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Notifications\TicketRepliedNotification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Support queue — status tabs with counts, thread view, reply
 * (marks answered + notifies the user), close/reopen, priority toggle.
 */
#[Layout('layouts.admin')]
class Tickets extends Component
{
    #[Url]
    public string $tab = 'open';

    #[Url(as: 'ticket')]
    public ?int $selectedId = null;

    public string $replyBody = '';

    public function setTab(string $tab): void
    {
        if (TicketStatus::tryFrom($tab) === null) {
            return;
        }

        $this->tab = $tab;
        $this->selectedId = null;
        $this->replyBody = '';
        $this->resetErrorBag();
    }

    public function select(int $ticketId): void
    {
        $this->selectedId = SupportTicket::findOrFail($ticketId)->id;
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
        $ticket = SupportTicket::findOrFail($this->selectedId);

        $this->validate([
            'replyBody' => ['required', 'string', 'min:2', 'max:3000'],
        ], attributes: [
            'replyBody' => __('reply'),
        ]);

        $ticket->replies()->create([
            'author_type' => 'admin',
            'author_id' => auth()->id(),
            'body' => strip_tags(trim($this->replyBody)),
        ]);

        $ticket->update(['status' => TicketStatus::Answered]);
        $ticket->user->notify(new TicketRepliedNotification($ticket));

        $this->replyBody = '';
        $this->dispatch('toast', message: __('Reply sent — the buyer has been notified.'));
    }

    public function close(int $ticketId): void
    {
        SupportTicket::findOrFail($ticketId)->update(['status' => TicketStatus::Closed]);

        $this->dispatch('toast', message: __('Ticket closed'));
    }

    public function reopen(int $ticketId): void
    {
        SupportTicket::findOrFail($ticketId)->update(['status' => TicketStatus::Open]);

        $this->dispatch('toast', message: __('Ticket reopened'));
    }

    public function togglePriority(int $ticketId): void
    {
        $ticket = SupportTicket::findOrFail($ticketId);

        $ticket->update([
            'priority' => $ticket->priority === TicketPriority::Urgent ? TicketPriority::Normal : TicketPriority::Urgent,
        ]);

        $this->dispatch('toast', message: $ticket->priority === TicketPriority::Urgent ? __('Marked urgent') : __('Marked normal'));
    }

    public function render()
    {
        $selected = $this->selectedId !== null
            ? SupportTicket::with(['replies', 'user'])->find($this->selectedId)
            : null;

        if ($this->selectedId !== null && $selected === null) {
            $this->reset(['selectedId', 'replyBody']);
        }

        return view('livewire.admin.support.tickets', [
            'counts' => SupportTicket::statusCounts(),
            'tickets' => SupportTicket::query()
                ->status(TicketStatus::from($this->tab))
                ->with('user')
                ->withCount('replies')
                ->orderByRaw("case when priority = 'urgent' then 0 else 1 end")
                ->latest('updated_at')
                ->get(),
            'selected' => $selected,
        ])->title(__('Support tickets'));
    }
}
