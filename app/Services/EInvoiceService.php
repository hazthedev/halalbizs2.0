<?php

namespace App\Services;

use App\Enums\EInvoiceStatus;
use App\Enums\EInvoiceType;
use App\Enums\PaymentStatus;
use App\Models\EInvoiceDocument;
use App\Models\Order;
use App\Models\Store;
use App\Models\SubOrder;
use App\Services\EInvoice\EInvoiceDocumentBuilder;
use App\Services\EInvoice\EInvoiceProvider;
use App\Services\EInvoice\EInvoiceResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Orchestrates e-invoice issuance (docs/ROADMAP.md M0.2). Each seller (store)
 * is the supplier. An individual e-invoice is issued per sub-order when the
 * buyer requested one or the sub-order meets the individual threshold; the rest
 * are B2C receipts that the monthly consolidator batches into one document per
 * store. Reads only sacred snapshots; idempotent; never throws into the payment
 * path (callers catch).
 */
class EInvoiceService
{
    public function __construct(
        private EInvoiceProvider $provider,
        private EInvoiceDocumentBuilder $builder,
    ) {}

    /**
     * Fired when an order becomes paid. Issues individual e-invoices for the
     * sub-orders that qualify; the rest wait for consolidation.
     */
    public function issueForOrder(Order $order): void
    {
        if ($order->payment_status !== PaymentStatus::Paid) {
            return;
        }

        $order->loadMissing('subOrders');

        foreach ($order->subOrders as $subOrder) {
            if ($this->requiresIndividual($order, $subOrder)) {
                $this->issueIndividual($subOrder);
            }
        }
    }

    /** True when this sub-order must be its own e-invoice (not consolidated). */
    public function requiresIndividual(Order $order, SubOrder $subOrder): bool
    {
        return (bool) $order->einvoice_requested
            || $subOrder->total_sen >= (int) config('einvoice.individual_threshold_sen');
    }

    /** Issue (or skip, if already issued) an individual e-invoice for a sub-order. */
    public function issueIndividual(SubOrder $subOrder): EInvoiceDocument
    {
        $existing = EInvoiceDocument::where('sub_order_id', $subOrder->id)->first();

        if ($existing !== null) {
            return $existing; // idempotent
        }

        $document = EInvoiceDocument::create([
            'store_id' => $subOrder->store_id,
            'sub_order_id' => $subOrder->id,
            'order_id' => $subOrder->order_id,
            'provider' => $this->provider->name(),
            'type' => EInvoiceType::Individual,
            'status' => EInvoiceStatus::Pending,
            'total_sen' => $subOrder->total_sen,
            'tax_sen' => $subOrder->tax_sen,
        ]);

        $payload = $this->builder->forSubOrder($subOrder);

        return $this->persist($document, $this->provider->submit($payload));
    }

    /**
     * Monthly B2C consolidation: one document per store for the un-individually
     * invoiced, paid sub-orders in the period. Idempotent per (store, period).
     *
     * @return int number of consolidated documents issued
     */
    public function consolidate(string $period): int
    {
        [$start, $end] = $this->periodBounds($period);

        $eligible = SubOrder::query()
            ->whereDoesntHave('einvoiceDocument')
            ->whereHas('order', fn ($q) => $q
                ->where('payment_status', PaymentStatus::Paid)
                ->whereBetween('paid_at', [$start, $end])
                ->where('einvoice_requested', false))
            ->where('total_sen', '<', (int) config('einvoice.individual_threshold_sen'))
            ->with(['store'])
            ->get()
            ->groupBy('store_id');

        $issued = 0;

        foreach ($eligible as $storeId => $subOrders) {
            if ($this->consolidateStore($subOrders->first()->store, $period, $subOrders) !== null) {
                $issued++;
            }
        }

        return $issued;
    }

    /**
     * @param  Collection<int, SubOrder>  $subOrders
     */
    private function consolidateStore(Store $store, string $period, Collection $subOrders): ?EInvoiceDocument
    {
        $already = EInvoiceDocument::where('store_id', $store->id)
            ->where('period', $period)
            ->where('type', EInvoiceType::Consolidated)
            ->exists();

        if ($already || $subOrders->isEmpty()) {
            return null;
        }

        $document = EInvoiceDocument::create([
            'store_id' => $store->id,
            'provider' => $this->provider->name(),
            'type' => EInvoiceType::Consolidated,
            'period' => $period,
            'status' => EInvoiceStatus::Pending,
            'total_sen' => (int) $subOrders->sum('total_sen'),
            'tax_sen' => (int) $subOrders->sum('tax_sen'),
        ]);

        $payload = $this->builder->forConsolidated($store, $period, $subOrders);

        return $this->persist($document, $this->provider->submit($payload));
    }

    private function persist(EInvoiceDocument $document, EInvoiceResult $result): EInvoiceDocument
    {
        $document->forceFill([
            'status' => $result->status,
            'submission_uid' => $result->submissionUid,
            'uin' => $result->uin,
            'validation_url' => $result->validationUrl,
            'error' => $result->error,
            'submitted_at' => $result->status === EInvoiceStatus::Pending ? null : now(),
            'validated_at' => $result->status === EInvoiceStatus::Valid ? now() : null,
        ])->save();

        return $document;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodBounds(string $period): array
    {
        $month = Carbon::createFromFormat('Y-m', $period)->startOfMonth();

        return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
    }
}
