<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Buyer↔store chat thread — one row per pair (unique buyer_id+store_id).
 * Always create/send through App\Services\ChatService so the own-shop
 * guard, validation and notifications stay in one place.
 */
class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['buyer_id', 'store_id', 'last_message_at'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** A thread opens on its latest messages only; older history is not loaded. */
    public const THREAD_WINDOW = 100;

    /** The latest THREAD_WINDOW messages, oldest first, with the context product. */
    public function loadThreadWindow(): static
    {
        $this->load(['messages' => fn ($query) => $query->reorder()->latest('id')->limit(self::THREAD_WINDOW)->with('product.media')]);
        $this->setRelation('messages', $this->messages->reverse()->values());

        return $this;
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at')->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /** Unread = messages the OTHER side sent that this side hasn't read. */
    public function unreadCountFor(string $side): int
    {
        return $this->messages()
            ->whereNull('read_at')
            ->where('sender_type', $side === 'buyer' ? 'seller' : 'buyer')
            ->count();
    }
}
