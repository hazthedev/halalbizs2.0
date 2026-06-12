<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketReply extends Model
{
    use HasFactory;

    /** Replies are immutable — created_at only. */
    public const UPDATED_AT = null;

    protected $fillable = ['ticket_id', 'author_type', 'author_id', 'body'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isFromSupport(): bool
    {
        return $this->author_type === 'admin';
    }
}
