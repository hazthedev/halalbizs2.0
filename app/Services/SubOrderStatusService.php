<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\SubOrderStatus;
use App\Events\SubOrderStatusChanged;
use App\Models\SubOrder;
use App\Settings\OrderSettings;
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
    ): SubOrder {
        $from = $subOrder->status;

        if (! $this->canTransition($subOrder, $to)) {
            throw new InvalidArgumentException(
                "Invalid sub-order transition [{$from->value} → {$to->value}] on {$subOrder->sub_order_no}."
            );
        }

        $subOrder->forceFill(array_merge(
            ['status' => $to],
            $this->timestampsFor($subOrder, $to),
        ))->save();

        $this->writeHistory($subOrder, $from, $to, $actorType, $actorId, $note);

        SubOrderStatusChanged::dispatch($subOrder, $from, $to, $actorType);

        return $subOrder;
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
