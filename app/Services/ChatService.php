<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\NewChatMessageNotification;
use Illuminate\Validation\ValidationException;

/**
 * Buyer↔seller chat (one thread per buyer+store pair). All chat writes go
 * through here: the own-shop guard, body validation, last_message_at
 * bookkeeping and the database-first notification to the other side.
 */
class ChatService
{
    /**
     * Find or start the buyer's thread with a store.
     *
     * @throws ValidationException when the buyer owns the store
     */
    public function openConversation(User $buyer, Store $store): Conversation
    {
        if ($store->user_id === $buyer->id) {
            throw ValidationException::withMessages([
                'conversation' => __("You can't chat with your own shop."),
            ]);
        }

        return Conversation::firstOrCreate([
            'buyer_id' => $buyer->id,
            'store_id' => $store->id,
        ]);
    }

    /**
     * Append a message and notify the OTHER side. The notification is
     * database-first; mail is rate-limited inside the notification's via()
     * (max one mail per conversation per recipient side every 10 minutes).
     *
     * @param  'buyer'|'seller'  $side
     * @param  ?Product  $context  optional "asking about this item" chip
     *
     * @throws ValidationException on a blank or over-long body
     */
    public function sendMessage(Conversation $conversation, string $side, User $sender, string $body, ?Product $context = null): Message
    {
        if (! in_array($side, ['buyer', 'seller'], true)) {
            throw new \InvalidArgumentException("Invalid conversation side [{$side}].");
        }

        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages(['body' => __('Type a message first.')]);
        }

        if (mb_strlen($body) > 2000) {
            throw ValidationException::withMessages(['body' => __('Messages can be up to 2,000 characters.')]);
        }

        $message = $conversation->messages()->create([
            'sender_type' => $side,
            'sender_id' => $sender->id,
            'body' => $body,
            'product_id' => $context?->id,
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        $recipientSide = $side === 'buyer' ? 'seller' : 'buyer';
        $recipient = $recipientSide === 'seller' ? $conversation->store?->user : $conversation->buyer;

        $recipient?->notify(new NewChatMessageNotification($conversation, $message, $recipientSide));

        return $message;
    }

    /** The given side has read the thread — clear the other side's unread. */
    public function markRead(Conversation $conversation, string $side): void
    {
        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_type', $side === 'buyer' ? 'seller' : 'buyer')
            ->update(['read_at' => now()]);
    }
}
