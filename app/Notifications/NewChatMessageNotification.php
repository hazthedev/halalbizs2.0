<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * New chat message — DATABASE-FIRST (the bell shows it instantly).
 *
 * Mail is throttled inside via(): at most ONE mail per conversation per
 * recipient side every 10 minutes (RateLimiter, cache-backed), so a chat
 * burst lands as a single "you have new messages" email instead of spam.
 */
class NewChatMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const MAIL_THROTTLE_SECONDS = 600;

    public function __construct(
        public Conversation $conversation,
        public Message $message,
        public string $audience, // recipient side: buyer | seller
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $mailAllowed = RateLimiter::attempt(
            "chat-mail:{$this->conversation->id}:{$this->audience}",
            1,
            fn () => true,
            self::MAIL_THROTTLE_SECONDS,
        );

        if ($mailAllowed) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New message from :name', ['name' => $this->senderName()]))
            ->line(__(':name sent you a message:', ['name' => $this->senderName()]))
            ->line('"'.Str::limit($this->message->body, 160).'"')
            ->action(__('Reply'), $this->url())
            ->line(__('We bundle chat emails — you\'ll get at most one every 10 minutes per conversation.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => __('New message from :name', ['name' => $this->senderName()]),
            'detail' => Str::limit($this->message->body, 120),
            'conversation_id' => $this->conversation->id,
            'url' => $this->url(),
        ];
    }

    private function senderName(): string
    {
        // The recipient is the buyer → the sender is the store, and vice versa.
        return $this->audience === 'buyer'
            ? ($this->conversation->store?->name ?? __('Seller'))
            : ($this->conversation->buyer?->name ?? __('Buyer'));
    }

    private function url(): string
    {
        return $this->audience === 'seller'
            ? route('seller.messages', ['conversation' => $this->conversation->id])
            : route('account.messages', ['store' => $this->conversation->store_id]);
    }
}
