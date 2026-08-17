<?php

namespace App\Jobs;

use App\Models\SubOrder;
use App\Services\AfterShipTrackingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RegisterAfterShipTracking implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $subOrderId) {}

    public function handle(AfterShipTrackingService $tracking): void
    {
        $subOrder = SubOrder::find($this->subOrderId);

        if ($subOrder !== null) {
            $tracking->register($subOrder);
        }
    }
}
