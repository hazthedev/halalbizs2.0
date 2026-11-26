<?php

namespace App\Livewire\Admin\Support;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Concerns\WithTable;
use App\Models\SupportTicket;
use App\Notifications\TicketRepliedNotification;
use App\Support\JsonSearch;
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
    use WithTable;

    public const PER_PAGE = 20;

    #[Url]
    public string $tab = 'open';

    /** '' = all; a TicketCategory value filters (privacy = the privacy-request log). */
    #[Url(except: '')]
    public string $category = '';

    #[Url(as: 'ticket')]
    public ?int $selectedId = null;

    public string $replyBody = '';

    protected function tableFilters(): array
    {
        return ['tab', 'category'];
    }

    protected function tableSorts(): array
    {
        return ['id' => 'id', 'subject' => 'subject', 'priority' => 'priority', 'messages' => 'replies_count', 'updated' => 'updated_at'];
    }

    protected function tableDefaultOrder(): array
    {
        return ['updated_at' => 'desc'];
    }

    public function setTab(string $tab): void
    {
        if (TicketStatus::tryFrom($tab) === null) {
            return;
        }

        $this->tab = $tab;
        $this->resetPage();
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
        $ticket->user?->notify(new TicketRepliedNotification($ticket));

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
        $thread = $this->selectedId !== null
            ? SupportTicket::with(['replies', 'user'])->find($this->selectedId)
            : null;

        if ($this->selectedId !== null && $thread === null) {
            $this->reset(['selectedId', 'replyBody']);
        }

        return view('livewire.admin.support.tickets', [
            'counts' => SupportTicket::statusCounts(),
            'tickets' => $this->results(),
            'thread' => $thread,
        ])->title(__('Support tickets'));
    }

    private function results()
    {
        $term = JsonSearch::pattern($this->search);
        $query = SupportTicket::query()
            ->status(TicketStatus::tryFrom($this->tab) ?? TicketStatus::Open)
            ->with('user')
            ->withCount('replies')
            ->when(TicketCategory::tryFrom($this->category), fn ($q, $category) => $q->where('category', $category->value))
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereRaw(JsonSearch::clause('subject'), [$term])
                ->orWhereHas('user', fn ($u) => $u->whereRaw(JsonSearch::clause('name'), [$term])->orWhereRaw(JsonSearch::clause('email'), [$term]))));

        // Default view: urgent tickets first. Any chosen sort replaces that.
        if (! array_key_exists($this->sort, $this->tableSorts())) {
            $query->orderByRaw("case when priority = 'urgent' then 0 else 1 end");
        }

        return $this->sortTable($query)->paginate($this->tablePageSize());
    }
}
