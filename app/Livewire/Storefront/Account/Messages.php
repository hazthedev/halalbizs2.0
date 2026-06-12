<?php

namespace App\Livewire\Storefront\Account;

use App\Models\Conversation;
use App\Models\Product;
use App\Models\Store;
use App\Services\ChatService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Buyer inbox — two-pane chat inside the account shell. Entry points
 * (PDP chat button, order detail, notifications) deep-link with
 * ?store={id} (+ optional ?product={id} for the context chip).
 */
#[Layout('layouts.storefront')]
class Messages extends Component
{
    public ?int $conversationId = null;

    public string $body = '';

    /** Product attached to the next sent message ("asking about this item"). */
    public ?int $contextProductId = null;

    public function mount(ChatService $chat): void
    {
        $storeId = (int) request()->query('store', 0);

        if ($storeId > 0 && ($store = Store::query()->approved()->find($storeId)) !== null) {
            if ($store->user_id === auth()->id()) {
                $this->dispatch('toast', message: __("You can't chat with your own shop."), type: 'error');

                return;
            }

            $conversation = $chat->openConversation(auth()->user(), $store);
            $this->openConversation($conversation->id);

            $productId = (int) request()->query('product', 0);

            if ($productId > 0 && Product::query()->live()->where('store_id', $store->id)->whereKey($productId)->exists()) {
                $this->contextProductId = $productId;
            }
        }
    }

    public function openConversation(int $conversationId): void
    {
        $conversation = Conversation::query()
            ->where('buyer_id', auth()->id())
            ->find($conversationId);

        if ($conversation === null) {
            return;
        }

        $this->conversationId = $conversation->id;
        $this->reset('body');

        app(ChatService::class)->markRead($conversation, 'buyer');
    }

    /** Mobile back button — show the thread list again. */
    public function closeConversation(): void
    {
        $this->reset('conversationId', 'body', 'contextProductId');
    }

    public function removeContext(): void
    {
        $this->contextProductId = null;
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

        // Context chip only ever points at a product of THIS store.
        $context = $this->contextProductId !== null
            ? Product::query()->where('store_id', $conversation->store_id)->find($this->contextProductId)
            : null;

        $chat->sendMessage($conversation, 'buyer', auth()->user(), $this->body, $context);

        $this->reset('body', 'contextProductId');
        unset($this->activeConversation);
    }

    /** wire:poll target — pulls new messages and marks them read while open. */
    public function refreshThread(ChatService $chat): void
    {
        if ($this->activeConversation !== null) {
            $chat->markRead($this->activeConversation, 'buyer');
            unset($this->activeConversation);
        }
    }

    #[Computed]
    public function conversations()
    {
        return Conversation::query()
            ->where('buyer_id', auth()->id())
            ->with(['store.media', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn ($query) => $query->whereNull('read_at')->where('sender_type', 'seller')])
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
            ->where('buyer_id', auth()->id())
            ->with(['store.media', 'messages.product.media'])
            ->find($this->conversationId);
    }

    public function render()
    {
        return view('livewire.storefront.account.messages', [
            'contextProduct' => $this->contextProductId !== null ? Product::with('media')->find($this->contextProductId) : null,
        ])->title(__('Messages'));
    }
}
