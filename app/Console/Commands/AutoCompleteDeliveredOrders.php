<?php

namespace App\Console\Commands;

use App\Enums\ActorType;
use App\Enums\SubOrderStatus;
use App\Models\SubOrder;
use App\Services\SubOrderStatusService;
use Illuminate\Console\Command;

/**
 * docs/06 §E — hourly. Delivered + auto_complete window passed and no
 * open return → completed (starts the payout clock; ledger hook in M8).
 */
class AutoCompleteDeliveredOrders extends Command
{
    protected $signature = 'orders:auto-complete';

    protected $description = 'Complete delivered sub-orders whose confirmation window has passed';

    public function handle(SubOrderStatusService $statusService): int
    {
        SubOrder::where('status', SubOrderStatus::Delivered)
            ->whereNotNull('auto_complete_at')
            ->where('auto_complete_at', '<=', now())
            ->each(function (SubOrder $subOrder) use ($statusService) {
                $statusService->transition($subOrder, SubOrderStatus::Completed, ActorType::System, null, __('Auto-completed after the confirmation window'));
                $this->info("Completed {$subOrder->sub_order_no}.");
            });

        return self::SUCCESS;
    }
}
