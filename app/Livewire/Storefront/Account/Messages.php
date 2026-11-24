<?php

namespace App\Livewire\Storefront\Account;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\Store;
use App\Services\ChatService;
use App\Support\SendLimit;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Buyer inbox — two-pane chat inside the account shell. Entry points
 * (PDP chat button, order detail, notifications) deep-link with
 * ?store={id} (+ optional ?product={id} for the context chip).
 *
 * @property-read Conversation|null $activeConversation
 */
#[Layout('layouts.storefront')]
class Messages extends Component
{
    public ?int $conversationId = null;

    public string $body = '';

    /** Newest message id the last render showed (poll tick compares against it). */
    public int $seen = 0;

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

        // SEC-38: per user, per conversation (config/limits.php).
        $key = 'chat-send:'.auth()->id().':'.$conversation->id;

        // Context chip only ever points at a LIVE product of THIS store, as the
        // app API does: contextProductId is client-mutable (HB-G121).
        $context = $this->contextProductId !== null
            ? Product::query()->live()->where('store_id', $conversation->store_id)->find($this->contextProductId)
            : null;

        // HB-G240: counted only once the message is saved.
        $sent = SendLimit::attempt($key, config('limits.chat_messages_per_minute'), 60,
            fn () => $chat->sendMessage($conversation, 'buyer', auth()->user(), $this->body, $context));

        if (! $sent) {
            $this->addError('body', __('Too many requests. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        $this->reset('body', 'contextProductId');
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
            $chat->markRead($this->activeConversation, 'buyer');
            unset($this->activeConversation);
        }
    }

    private function newestMessageId(): int
    {
        return (int) Message::query()
            ->whereIn('conversation_id', Conversation::query()->where('buyer_id', auth()->id())->select('id'))
            ->max('id');
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
            ->with('store.media')
            ->find($this->conversationId)
            ?->loadThreadWindow();
    }

    public function render()
    {
        // Inbox already loaded by the view: no extra query.
        $this->seen = (int) $this->conversations->max(fn (Conversation $c) => $c->latestMessage?->id);

        return view('livewire.storefront.account.messages', [
            'contextProduct' => $this->scopedContextProduct(),
        ])->title(__('Messages'));
    }

    /**
     * The context chip's product, scoped the same way send() scopes it
     * (AL-L4): a live product belonging to the active conversation's store.
     * $contextProductId is client-mutable, so an unscoped find() here would
     * render any product's name/image — including unpublished ones.
     */
    private function scopedContextProduct(): ?Product
    {
        if ($this->contextProductId === null || $this->activeConversation === null) {
            return null;
        }

        return Product::query()
            ->live()
            ->where('store_id', $this->activeConversation->store_id)
            ->with('media')
            ->find($this->contextProductId);
    }
}
