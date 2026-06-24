<?php

namespace App\Services;

use App\Models\SubOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * PDF invoice per sub-order — generated on first request, cached to
 * storage (docs/06 §E).
 */
class InvoiceService
{
    public function path(SubOrder $subOrder): string
    {
        $path = "invoices/{$subOrder->sub_order_no}.pdf";

        if (! Storage::disk('local')->exists($path)) {
            $subOrder->loadMissing(['items', 'order.user', 'store']);

            $pdf = Pdf::loadView('pdf.invoice', ['subOrder' => $subOrder]);
            Storage::disk('local')->put($path, $pdf->output());
        }

        return Storage::disk('local')->path($path);
    }

    /** Seller picking document — items + ship-to + tracking, NO prices. */
    public function packingSlipPath(SubOrder $subOrder): string
    {
        $path = "packing-slips/{$subOrder->sub_order_no}.pdf";

        if (! Storage::disk('local')->exists($path)) {
            $subOrder->loadMissing(['items.variant', 'order', 'store']);

            $pdf = Pdf::loadView('pdf.packing-slip', ['subOrder' => $subOrder]);
            Storage::disk('local')->put($path, $pdf->output());
        }

        return Storage::disk('local')->path($path);
    }
}
