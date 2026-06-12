<?php

namespace App\Http\Controllers;

use App\Models\SubOrder;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoiceController extends Controller
{
    public function buyer(Request $request, SubOrder $subOrder, InvoiceService $invoices): BinaryFileResponse
    {
        abort_unless($subOrder->order->user_id === $request->user()->id, 403);

        return response()->download($invoices->path($subOrder), "{$subOrder->sub_order_no}.pdf");
    }

    public function seller(Request $request, SubOrder $subOrder, InvoiceService $invoices): BinaryFileResponse
    {
        abort_unless($subOrder->store_id === $request->user()->store?->id, 403);

        return response()->download($invoices->path($subOrder), "{$subOrder->sub_order_no}.pdf");
    }
}
