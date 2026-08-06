<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\SubOrderStatus;
use App\Events\SubOrderStatusChanged;
use App\Models\SubOrder;
use App\Settings\OrderSettings;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The ONLY path for sub-order status changes (CLAUDE.md hard rule 2).
 * Validates the transition, stamps timestamps, writes order_status_histories, fires events.
 */
class SubOrderStatusService
{
    /** @var array<string, list<SubOrderStatus>> */
    private const TRANSITIONS = [
        'pending_payment' => [SubOrderStatus::Confirmed, SubOrderStatus::Cancelled],
        'confirmed' => [SubOrderStatus::Processing, SubOrderStatus::Cancelled],
        'processing' => [SubOrderStatus::Shipped, SubOrderStatus::Cancelled],
        'shipped' => [SubOrderStatus::Delivered],
        'delivered' => [SubOrderStatus::Completed, SubOrderStatus::ReturnRequested],
        'completed' => [SubOrderStatus::ReturnRequested],
        'return_requested' => [SubOrderStatus::Returned, SubOrderStatus::Refunded, SubOrderStatus::Delivered, SubOrderStatus::Completed],
        'returned' => [SubOrderStatus::Refunded],
        'cancelled' => [],
        'refunded' => [],
    ];

    public function __construct(private OrderSettings $orderSettings) {}

    public function canTransition(SubOrder $subOrder, SubOrderStatus $to): bool
    {
        return in_array($to, self::TRANSITIONS[$subOrder->status->value], true);
    }

    public function transition(
        SubOrder $subOrder,
        SubOrderStatus $to,
        ActorType $actorType,
        ?int $actorId = null,
        ?string $note = null,
        ?Closure $after = null,
    ): SubOrder {
        return DB::transaction(function () use ($subOrder, $to, $actorType, $actorId, $note, $after) {
            // H2/M1 fix: re-fetch and lock the row FIRST, and validate/act on
            // THIS freshly-read status — never the caller's possibly-stale
            // in-memory copy. The buyer's confirmReceived() can race the hourly
            // auto-complete cron: both load the sub-order while it is still
            // `delivered`, so without a lock+re-read both would pass
            // canTransition() and both fire SubOrderStatusChanged, double-booking
            // the ledger. Under the lock, a racing duplicate call lands here
            // AFTER the first has already committed, so it sees the sub-order
            // already at `$to` and no-ops instead of re-applying — it is not
            // "illegal" (which still throws below), it is "already done".
            $locked = SubOrder::whereKey($subOrder->getKey())->lockForUpdate()->first();

            $from = $locked->status;

            if ($from === $to) {
                return $locked;
            }

            if (! $this->canTransition($locked, $to)) {
                throw new InvalidArgumentException(
                    "Invalid sub-order transition [{$from->value} → {$to->value}] on {$locked->sub_order_no}."
                );
            }

            // H3/M1 fix: status save + history write + event dispatch are one
            // atomic unit. A throwing SYNCHRONOUS listener (RecordLedgerOnCompletion)
            // now rolls the status change back too, instead of stranding the
            // sub-order at a Completed status with the sale/commission
            // permanently unbooked and un-rerunnable. Laravel nests this via a
            // SAVEPOINT when a caller (OrderService::cancel/markDelivered,
            // RefundService) already wraps the call in DB::transaction, so
            // there is no double-commit — an inner failure rolls back only to
            // the savepoint and rethrows, which unwinds the outer transaction too.
            $locked->forceFill(array_merge(
                ['status' => $to],
                $this->timestampsFor($locked, $to),
            ))->save();

            $this->writeHistory($locked, $from, $to, $actorType, $actorId, $note);

            SubOrderStatusChanged::dispatch($locked, $from, $to, $actorType);

            // M2 fix: side effects that must fire exactly ONCE per real transition
            // (restock, COD settlement) belong here, under the same lock — a
            // duplicate call returns at `$from === $to` above and never reaches
            // them. A caller running them after transition() returns cannot tell
            // the two apart and would re-apply them (double restock).
            $after?->__invoke($locked);

            return $locked;
        });
    }

    /**
     * Initial-state insert (checkout): no validation, but the history row is still written.
     */
    public function initial(SubOrder $subOrder, ActorType $actorType, ?int $actorId = null): void
    {
        $this->writeHistory($subOrder, null, $subOrder->status, $actorType, $actorId, null);

        SubOrderStatusChanged::dispatch($subOrder, null, $subOrder->status, $actorType);
    }

    private function timestampsFor(SubOrder $subOrder, SubOrderStatus $to): array
    {
        $now = now();

        return match ($to) {
            SubOrderStatus::Shipped => ['shipped_at' => $now],
            SubOrderStatus::Delivered => [
                'delivered_at' => $now,
                'auto_complete_at' => $now->copy()->addDays($this->orderSettings->auto_complete_days),
            ],
            SubOrderStatus::Completed => ['completed_at' => $now],
            SubOrderStatus::Cancelled => ['cancelled_at' => $now],
            default => [],
        };
    }

    private function writeHistory(
        SubOrder $subOrder,
        ?SubOrderStatus $from,
        SubOrderStatus $to,
        ActorType $actorType,
        ?int $actorId,
        ?string $note,
    ): void {
        $subOrder->statusHistories()->create([
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
