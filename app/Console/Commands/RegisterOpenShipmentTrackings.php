<?php

namespace App\Console\Commands;

use App\Enums\SubOrderStatus;
use App\Jobs\RegisterAfterShipTracking;
use App\Models\SubOrder;
use App\Services\AfterShipTrackingService;
use Illuminate\Console\Command;

class RegisterOpenShipmentTrackings extends Command
{
    protected $signature = 'tracking:register-open';

    protected $description = 'Register eligible open shipments with the configured tracking provider';

    public function handle(AfterShipTrackingService $tracking): int
    {
        if (! $tracking->configured()) {
            $this->components->info('Shipment tracking is not configured; nothing was queued.');

            return self::SUCCESS;
        }

        $queued = 0;

        SubOrder::query()
            ->whereIn('status', [SubOrderStatus::Shipped, SubOrderStatus::Delivered])
            ->whereNotNull('tracking_no')
            ->whereNull('tracking_provider_id')
            ->select('id')
            ->chunkById(200, function ($subOrders) use (&$queued) {
                foreach ($subOrders as $subOrder) {
                    RegisterAfterShipTracking::dispatch($subOrder->id);
                    $queued++;
                }
            });

        $this->components->info("Queued {$queued} shipment(s) for tracking registration.");

        return self::SUCCESS;
    }
}
