<?php

namespace App\Console\Commands;

use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Models\Store;
use App\Models\StoreLedgerEntry;
use App\Notifications\CommissionStatementNotification;
use App\Services\LedgerService;
use Illuminate\Console\Command;

/** On the 1st: tell every store with a negative available balance what it owes. */
class SendCommissionStatements extends Command
{
    protected $signature = 'ledger:send-statements';

    protected $description = 'Notify stores with a negative available balance of last month\'s commission and the total owed';

    public function handle(): int
    {
        $from = now()->subMonthNoOverflow()->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $sent = 0;

        Store::query()->with('user')->get()->each(function (Store $store) use ($from, $to, &$sent) {
            // Net of held money: a refund during the hold dips Available below
            // zero while the held sale still covers it — that is not a debt.
            $owed = app(LedgerService::class)->owedSen($store);

            if ($owed === 0 || $store->user === null) {
                return;
            }

            $month = (int) StoreLedgerEntry::query()
                ->where('store_id', $store->id)
                ->where('type', LedgerEntryType::Commission)
                ->whereHas('subOrder.order', fn ($q) => $q->where('payment_method', PaymentMethod::Cod->value))
                ->whereBetween('created_at', [$from, $to])
                ->sum('amount_sen');

            $store->user->notify(new CommissionStatementNotification($store, $from->format('F Y'), abs($month), $owed));
            $sent++;
        });

        $this->info("Sent {$sent} statement(s).");

        return self::SUCCESS;
    }
}
