<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\EInvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * Issues individual e-invoices for the qualifying sub-orders of a paid order.
 * Queued, so it never blocks payment fulfilment and cannot roll back a
 * confirmed payment — it runs outside that transaction.
 */
class IssueEInvoiceOnOrderPaid implements ShouldQueue
{
    public $queue = 'einvoice';

    /**
     * M-12: three tries with backoff, and NO try/catch around the body.
     *
     * This used to swallow Throwable and return normally, so the queue recorded
     * success and `--tries=3` on the worker was dead config — the one recovery
     * mechanism these have was disabled by the thing meant to protect them. The
     * docblock justified it as "a failure must never roll back a completed
     * order", but that already holds: this is ShouldQueue, so it runs OUTSIDE
     * the transaction that completed the order. Letting it throw fails the JOB,
     * not the order.
     */
    public $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(OrderPaid $event): void
    {
        app(EInvoiceService::class)->issueForOrder($event->order);
    }
}
