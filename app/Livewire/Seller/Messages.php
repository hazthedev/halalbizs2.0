<?php

namespace App\Livewire\Seller;

use App\Livewire\Concerns\CurrentStore;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Seller inbox — strictly scoped to the current store (CurrentStore):
 * every conversation query filters on store_id. Deep-linkable with
 * ?conversation={id} (chat notifications point here).
 *
 * @property-read Conversation|null $activeConversation
 */
#[Layout('layouts.seller')]
class Messages extends Component
{
    use CurrentStore;

    public ?int $conversationId = null;

    public string $body = '';

    /** Newest message id the last render showed (poll tick compares against it). */
    public int $seen = 0;

    public function mount(): void
    {
        $conversationId = (int) request()->query('conversation', 0);

        if ($conversationId > 0) {
            $this->openConversation($conversationId);
        }
    }

    public function openConversation(int $conversationId): void
    {
        $conversation = Conversation::query()
            ->where('store_id', $this->currentStore()->id)
            ->find($conversationId);

        if ($conversation === null) {
            return;
        }

        $this->conversationId = $conversation->id;
        $this->reset('body');

        app(ChatService::class)->markRead($conversation, 'seller');
    }

    /** Mobile back button — show the thread list again. */
    public function closeConversation(): void
    {
        $this->reset('conversationId', 'body');
    }

    public function send(ChatService $chat): void
    {
        $conversation = $this->activeConversation;

        if ($conversation === null) {
            return;
        }

        $this->validate(
            ['body' => ['required', 'string', 'max:2000']],
            [
                'body.required' => __('Type a message first.'),
                'body.max' => __('Messages can be up to 2,000 characters.'),
            ],
        );

        $chat->sendMessage($conversation, 'seller', auth()->user(), $this->body);

        $this->reset('body');
        unset($this->activeConversation);
    }

    /**
     * wire:poll target — pulls new messages and marks them read while open.
     * $seen is the newest message id the last render showed; when nothing newer
     * exists the tick is one cheap query and the component does not re-render.
     */
    public function refreshThread(ChatService $chat): void
    {
        if ($this->newestMessageId() <= $this->seen) {
            $this->skipRender();

            return;
        }

        if ($this->activeConversation !== null) {
            $chat->markRead($this->activeConversation, 'seller');
            unset($this->activeConversation);
        }
    }

    private function newestMessageId(): int
    {
        return (int) Message::query()
            ->whereIn('conversation_id', Conversation::query()->where('store_id', $this->currentStore()->id)->select('id'))
            ->max('id');
    }

    #[Computed]
    public function conversations()
    {
        return Conversation::query()
            ->where('store_id', $this->currentStore()->id)
            ->with(['buyer', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn ($query) => $query->whereNull('read_at')->where('sender_type', 'buyer')])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function activeConversation(): ?Conversation
    {
        if ($this->conversationId === null) {
            return null;
        }

        return Conversation::query()
            ->where('store_id', $this->currentStore()->id)
            ->with('buyer')
            ->find($this->conversationId)
            ?->loadThreadWindow();
    }

    public function render()
    {
        // Inbox already loaded by the view: no extra query.
        $this->seen = (int) $this->conversations->max(fn (Conversation $c) => $c->latestMessage?->id);

        return view('livewire.seller.messages')->title(__('Messages'));
    }
}
