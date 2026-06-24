<?php

namespace App\Console\Commands;

use App\Services\EInvoiceService;
use Illuminate\Console\Command;

/**
 * Monthly LHDN B2C consolidation: batches each store's un-requested, paid
 * sub-orders for the period into one consolidated e-invoice. Runs on the 1st
 * for the previous month (within the 7-day window). Idempotent.
 */
class ConsolidateEInvoices extends Command
{
    protected $signature = 'einvoice:consolidate {period? : Period as YYYY-MM (defaults to last month)}';

    protected $description = 'Issue consolidated B2C e-invoices for the given month';

    public function handle(EInvoiceService $service): int
    {
        $period = $this->argument('period') ?? now()->subMonthNoOverflow()->format('Y-m');

        $count = $service->consolidate($period);

        $this->info("Consolidated e-invoices issued for {$period}: {$count}");

        return self::SUCCESS;
    }
}
