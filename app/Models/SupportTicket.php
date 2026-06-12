<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'subject', 'status', 'priority'];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class, 'ticket_id')->orderBy('created_at')->orderBy('id');
    }

    public function isClosed(): bool
    {
        return $this->status === TicketStatus::Closed;
    }

    #[Scope]
    protected function status(Builder $query, TicketStatus $status): void
    {
        $query->where('status', $status);
    }

    /** Open tickets are the admin queue — a user spoke last and waits on support. */
    #[Scope]
    protected function awaitingSupport(Builder $query): void
    {
        $query->where('status', TicketStatus::Open);
    }

    /** @return array<string, int> keyed by status value, every status present. */
    public static function statusCounts(): array
    {
        $counts = static::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(TicketStatus::cases())
            ->mapWithKeys(fn (TicketStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }
}
