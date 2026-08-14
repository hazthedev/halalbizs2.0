<?php

namespace App\Livewire\Seller;

use App\Enums\CertificateStatus;
use App\Livewire\Concerns\CurrentStore;
use App\Models\HalalCertificate;
use App\Models\HalalCertificateEvent;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The seller's halal certificates — the write path audit H-6 found missing.
 *
 * Until this screen existed, halal_certificates was populated by two demo
 * seeders and nothing else, so at a real cutover the entire trust layer of the
 * marketplace was empty with no way to fill it.
 *
 * ⚠ INCOMPLETE UNTIL THE PUBLISH-GATE CHANGE LANDS. Renewing sends the record
 * back to Pending, so halalVerdict() reads 'pending' and the badge drops for
 * the length of our review. That makes the 90-day renewal nudge self-defeating
 * — renew early, lose the badge early. The fix is the submitted_at grace that
 * ships with the gate config; it belongs there because that is where the same
 * "our review latency must not cost a seller anything" rule already lives.
 *
 * A RENEWAL IS AN EDIT TO THE SAME ROW, not a new record. The number carries a
 * unique index because a printed certificate number is unique in the real
 * world, and /certificate-register looks a certificate up BY that number — so
 * one row per number, with halal_certificate_events as its history. Re-submitting
 * new validity dates sends the same row back to Pending.
 */
#[Layout('layouts.seller')]
class Certificates extends Component
{
    use CurrentStore, WithFileUploads;

    /** The certificate being created or renewed; null when the form is closed. */
    public ?int $editing = null;

    public bool $creating = false;

    public string $number = '';

    public string $issuingBody = '';

    public string $holderName = '';

    public string $scheme = '';

    public string $scopeNote = '';

    public string $validFrom = '';

    public string $validTo = '';

    public string $facility = '';

    public bool $dedicatedFacility = false;

    public bool $exportPaperwork = false;

    public ?TemporaryUploadedFile $document = null;

    /** SKU binding: product ids covered by the certificate open in $editing. */
    public array $covered = [];

    public function startCreate(): void
    {
        $this->resetForm();
        $this->creating = true;
    }

    /** Renew or correct an existing certificate — same row, back through review. */
    public function edit(int $id): void
    {
        $certificate = $this->certificates()->findOrFail($id);

        $this->resetForm();
        $this->editing = $id;
        $this->number = $certificate->number;
        $this->issuingBody = $certificate->issuing_body;
        $this->holderName = $certificate->holder_name;
        $this->scheme = (string) $certificate->scheme;
        $this->scopeNote = (string) $certificate->scope_note;
        $this->validFrom = $certificate->valid_from->toDateString();
        $this->validTo = $certificate->valid_to->toDateString();
        $this->facility = (string) $certificate->facility;
        $this->dedicatedFacility = $certificate->dedicated_facility;
        $this->exportPaperwork = $certificate->export_paperwork;
        $this->covered = $certificate->products()->pluck('products.id')->all();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $certificate = $this->editing === null ? null : $this->certificates()->findOrFail($this->editing);

        $data = $this->validate([
            // Unique across the marketplace, ignoring this row on a renewal. A
            // printed number IS globally unique, so a collision means either a
            // typo or someone else's certificate — say which, don't 500.
            'number' => ['required', 'string', 'max:60', Rule::unique('halal_certificates', 'number')->ignore($certificate?->id)],
            'issuingBody' => ['required', Rule::in(array_keys(HalalCertificate::BODIES))],
            'holderName' => ['required', 'string', 'max:150'],
            'scheme' => ['nullable', 'string', 'max:60'],
            'scopeNote' => ['nullable', 'string', 'max:255'],
            'validFrom' => ['required', 'date'],
            'validTo' => ['required', 'date', 'after:validFrom'],
            'facility' => ['nullable', 'string', 'max:150'],
            'dedicatedFacility' => ['boolean'],
            'exportPaperwork' => ['boolean'],
            // Required on a first submission, optional on a renewal that is only
            // correcting a typo — the previous scan stays attached.
            'document' => [$certificate?->getFirstMedia('document') === null ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'covered' => ['array'],
            'covered.*' => ['integer'],
        ], [
            'number.unique' => __('That certificate number is already registered. If it is yours, it is on this page already; if not, contact support.'),
            'validTo.after' => __('The expiry date must come after the issue date.'),
        ]);

        // A term longer than five years is a typo, not a certificate: JAKIM
        // issues 1, 2 or 3 years, and 5 only to long-standing holders.
        if (now()->parse($data['validFrom'])->diffInYears(now()->parse($data['validTo'])) > 5) {
            $this->addError('validTo', __('That is more than a five-year term — check the dates on the certificate.'));

            return;
        }

        DB::transaction(function () use ($certificate, $data) {
            $store = $this->currentStore();

            $certificate ??= new HalalCertificate;
            $certificate->fill([
                'number' => $data['number'],
                'issuing_body' => $data['issuingBody'],
                'issuing_body_name' => HalalCertificate::BODIES[$data['issuingBody']]['name'],
                'holder_name' => $data['holderName'],
                'scheme' => $data['scheme'] ?: null,
                'scope_note' => $data['scopeNote'] ?: null,
                'valid_from' => $data['validFrom'],
                'valid_to' => $data['validTo'],
                'facility' => $data['facility'] ?: null,
                'dedicated_facility' => $data['dedicatedFacility'],
                'export_paperwork' => $data['exportPaperwork'],
            ]);

            // store_id and the review fields are guarded, never filled: the
            // seller must not be able to submit a certificate already approved,
            // or one belonging to another store.
            $certificate->store_id = $store->id;
            $certificate->forceFill([
                'status' => CertificateStatus::Pending,
                'submitted_at' => now(),
                'reviewed_at' => null,
                'reviewed_by' => null,
                'review_note' => null,
                // A renewal moves valid_to forward, so the previous nudge is
                // spent. Clearing it here means the seller is warned again
                // against the NEW date rather than being silently skipped.
                'renewal_notified_at' => null,
            ])->save();

            if ($this->document !== null) {
                $certificate->addMedia($this->document->getRealPath())
                    ->usingFileName('certificate-'.$certificate->number.'.'.$this->document->getClientOriginalExtension())
                    ->toMediaCollection('document');
            }

            $this->syncCoveredProducts($certificate, $data['covered']);

            HalalCertificateEvent::create([
                'halal_certificate_id' => $certificate->id,
                'occurred_on' => now()->toDateString(),
                'summary' => $certificate->wasRecentlyCreated
                    ? __('Submitted for review')
                    : __('Renewal submitted for review'),
            ]);
        });

        $this->resetForm();
        $this->dispatch('toast', message: __('Sent for review. Your products stay on sale while we check it.'));
    }

    /**
     * Bind the certificate to the seller's own SKUs.
     *
     * Every id is re-resolved through the store's own products, so a crafted
     * payload cannot attach someone else's SKU. The model has a saving guard
     * too — this is the UI half of the same rule, not the only copy of it.
     */
    private function syncCoveredProducts(HalalCertificate $certificate, array $covered): void
    {
        $ids = $this->storeProducts()->whereIn('id', $covered)->pluck('id');

        Product::query()
            ->where('halal_certificate_id', $certificate->id)
            ->whereNotIn('id', $ids)
            ->update(['halal_certificate_id' => null]);

        $this->storeProducts()->whereIn('id', $ids)->update([
            'halal_certificate_id' => $certificate->id,
            'halal_cert_number' => $certificate->number,
            'halal_cert_expiry' => $certificate->valid_to,
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['editing', 'creating', 'number', 'issuingBody', 'holderName', 'scheme',
            'scopeNote', 'validFrom', 'validTo', 'facility', 'dedicatedFacility',
            'exportPaperwork', 'document', 'covered']);
        $this->resetErrorBag();
    }

    private function certificates()
    {
        return $this->currentStore()->halalCertificates();
    }

    private function storeProducts()
    {
        return Product::query()->where('store_id', $this->currentStore()->id);
    }

    public function render()
    {
        return view('livewire.seller.certificates', [
            'certificates' => $this->certificates()->with('products:id,halal_certificate_id')->get(),
            'products' => $this->storeProducts()->orderBy('name')->get(['id', 'name', 'halal_certificate_id']),
            'bodies' => HalalCertificate::BODIES,
        ])->title(__('Halal certificates'));
    }
}
