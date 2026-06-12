<?php

namespace App\Livewire\Seller;

use App\Livewire\Concerns\CurrentStore;
use App\Models\Conversation;
use App\Services\ChatService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Seller inbox — strictly scoped to the current store (CurrentStore):
 * every conversation query filters on store_id. Deep-linkable with
 * ?conversation={id} (chat notifications point here).
 */
#[Layout('layouts.seller')]
class Messages extends Component
{
    use CurrentStore;

    public ?int $conversationId = null;

    public string $body = '';

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

    /** wire:poll target — pulls new messages and marks them read while open. */
    public function refreshThread(ChatService $chat): void
    {
        if ($this->activeConversation !== null) {
            $chat->markRead($this->activeConversation, 'seller');
            unset($this->activeConversation);
        }
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
            ->with(['buyer', 'messages.product.media'])
            ->find($this->conversationId);
    }

    public function render()
    {
        return view('livewire.seller.messages')->title(__('Messages'));
    }
}
