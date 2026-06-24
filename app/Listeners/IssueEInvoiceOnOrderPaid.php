<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\EInvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Issues individual e-invoices for the qualifying sub-orders of a paid order.
 * Queued so it never blocks payment fulfilment, and defensive so an e-invoice
 * failure can never roll back a confirmed payment.
 */
class IssueEInvoiceOnOrderPaid implements ShouldQueue
{
    public $queue = 'einvoice';

    public function handle(OrderPaid $event): void
    {
        try {
            app(EInvoiceService::class)->issueForOrder($event->order);
        } catch (Throwable $e) {
            Log::error('E-invoice issuance failed.', [
                'order_no' => $event->order->order_no,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
