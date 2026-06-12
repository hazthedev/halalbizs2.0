<?php

namespace App\Console\Commands;

use App\Enums\ReturnStatus;
use App\Models\ReturnRequest;
use Illuminate\Console\Command;

/**
 * docs/09 §D — hourly. Sellers get return_seller_response_hours to answer
 * a return request; silence escalates it to the admin queue (no extra
 * notification in v1 — the queue itself is the surface).
 */
class AutoEscalateReturns extends Command
{
    protected $signature = 'returns:auto-escalate';

    protected $description = 'Escalate return requests the seller has not answered within the response window';

    public function handle(): int
    {
        ReturnRequest::query()
            ->where('status', ReturnStatus::Requested)
            ->where('respond_by', '<=', now())
            ->each(function (ReturnRequest $request) {
                $request->update(['status' => ReturnStatus::Escalated, 'escalated_at' => now()]);
                $this->info("Escalated return request #{$request->id} ({$request->subOrder?->sub_order_no}).");
            });

        return self::SUCCESS;
    }
}
