<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chat message — immutable once sent (created_at only, no updated_at).
 * sender_type is the conversation side ('buyer' | 'seller'); sender_id is
 * the user who typed it.
 */
class Message extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['conversation_id', 'sender_type', 'sender_id', 'body', 'product_id', 'read_at'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** Optional product context chip — null after the product is deleted. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
