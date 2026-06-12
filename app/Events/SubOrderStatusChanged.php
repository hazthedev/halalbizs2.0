<?php

namespace App\Events;

use App\Enums\ActorType;
use App\Enums\SubOrderStatus;
use App\Models\SubOrder;
use Illuminate\Foundation\Events\Dispatchable;

class SubOrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public SubOrder $subOrder,
        public ?SubOrderStatus $from,
        public SubOrderStatus $to,
        public ActorType $actorType,
    ) {}
}
