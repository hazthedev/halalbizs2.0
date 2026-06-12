<?php

namespace App\Listeners;

use App\Enums\SubOrderStatus;
use App\Events\SubOrderStatusChanged;
use App\Services\LedgerService;

/**
 * The payout clock starts at completion (docs/09 §A).
 */
class RecordLedgerOnCompletion
{
    public function __construct(private LedgerService $ledger) {}

    public function handle(SubOrderStatusChanged $event): void
    {
        if ($event->to === SubOrderStatus::Completed) {
            $this->ledger->recordCompletion($event->subOrder);
        }
    }
}
