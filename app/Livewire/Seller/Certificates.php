<?php

namespace App\Livewire\Seller;

use App\Enums\CertificateStatus;
use App\Livewire\Concerns\CurrentStore;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HalalCertificate;
use App\Models\HalalCertificateEvent;
use App\Models\Product;
use App\Services\ProductWriteService;
use App\Services\Whip\WhipDirectory;
use App\Support\DisplayTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

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

    /** Company registration (SSM) number: how WHIP ties a certificate to this seller. */
    public string $registrationNo = '';

    public ?TemporaryUploadedFile $document = null;

    /** SKU binding: product ids covered by the certificate open in $editing. */
    public array $covered = [];

    public array $whipCertificates = [];

    public bool $whipSearched = false;

    public ?int $importCategoryId = null;

    public function importWhipProducts(int $certificateId): void
    {
        $this->resetErrorBag('whip');
        $certificate = $this->certificates()->findOrFail($certificateId);
        $store = $this->currentStore()->fresh();
        abort_unless($certificate->source === 'whip' && $store->registration_verified_at !== null, 403);
        $this->validate(['importCategoryId' => ['required', 'integer']]);
        if ($problem = ProductWriteService::categoryProblem($this->importCategoryId)) {
            $this->addError('importCategoryId', $problem);

            return;
        }
        $count = DB::transaction(function () use ($certificate, $store) {
            $store = $store->newQuery()->lockForUpdate()->findOrFail($store->id);
            $certificate = $this->certificates()->lockForUpdate()->findOrFail($certificate->id);
            abort_unless($certificate->source === 'whip' && $store->registration_verified_at !== null, 403);
            [$answer, $whip] = app(WhipDirectory::class)->check($certificate->number, $store->registration_no);
            if ($answer !== 'verified' || $whip->applicationId !== (int) $certificate->whip_application_id) {
                throw ValidationException::withMessages(['whip' => __('WHIP could not confirm this certificate for your company. Nothing was imported.')]);
            }
            $certificate->takeFromWhip($whip, 'verified')->save();
            $count = 0;
            foreach ($whip->products as $row) {
                if (Product::withTrashed()->where('store_id', $store->id)->where('whip_product_id', $row['id'])->exists()) {
                    continue;
                }
                $draft = app(ProductWriteService::class)->create($store, [
                    'name' => ['en' => $row['name']], 'description' => ['en' => ''],
                    'category_id' => $this->importCategoryId, 'sku' => $row['barcode'],
                ]);
                $draft->forceFill(['whip_product_id' => $row['id']])->fill([
                    'brand_id' => filled($row['brand']) ? Brand::where('is_active', true)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($row['brand']))])->orderBy('id')->value('id') : null,
                    'halal_certificate_id' => $certificate->id,
                    'halal_cert_number' => $certificate->number, 'halal_cert_expiry' => $certificate->valid_to,
                ])->save();
                $count++;
            }
            $certificate->bindProducts([...$certificate->products()->pluck('id'), ...($certificate->whip_unmatched_product_ids ?? [])], $whip);

            return $count;
        });
        $this->dispatch('toast', message: __(':count product drafts imported. Review each draft, add a price and photo, then publish.', ['count' => $count]));
    }

    public function findWhipCertificates(): void
    {
        $this->whipCertificates = [];
        $this->whipSearched = false;
        $this->resetErrorBag('whip');
        $store = $this->currentStore()->fresh();
        if (blank($store->registration_no)) {
            $this->validate(['registrationNo' => ['required', 'string', 'max:40']]);
            $store->forceFill(['registration_no' => trim($this->registrationNo)])->save();
        }
        if ($store->registration_verified_at === null) {
            $this->addError('whip', __('Your SSM number needs to be checked by our team before we can show your WHIP records.'));

            return;
        }
        try {
            $this->whipCertificates = array_map(fn ($c) => [
                'number' => $c->certificateNo, 'scope' => $c->scopeNote,
                'status' => $c->status, 'expiry' => $c->validTo,
                'can_link' => $c->answerFor($store->registration_no) === 'verified' && filled($c->companyName),
            ], app(WhipDirectory::class)->forCompany($store->registration_no));
            $this->whipSearched = true;
        } catch (Throwable) {
            $this->addError('whip', __('WHIP is unavailable. Please try again later.'));
        }
    }

    public function linkWhipCertificate(string $number): void
    {
        $this->resetErrorBag('whip');
        $store = $this->currentStore()->fresh();
        DB::transaction(function () use ($store, $number) {
            $store = $store->newQuery()->lockForUpdate()->findOrFail($store->id);
            abort_unless($store->registration_verified_at !== null && filled($store->registration_no), 403);
            $certificate = HalalCertificate::whereRaw('UPPER(TRIM(number)) = ?', [strtoupper(trim($number))])->lockForUpdate()->first();
            abort_if($certificate !== null && $certificate->store_id !== $store->id, 403);
            [$answer, $whip] = app(WhipDirectory::class)->check($number, $store->registration_no);
            if ($answer !== 'verified' || blank($whip?->companyName)) {
                throw ValidationException::withMessages(['whip' => __('WHIP could not confirm a complete, current certificate for your verified SSM number.')]);
            }
            $certificate ??= new HalalCertificate(['store_id' => $store->id, 'number' => $whip->certificateNo, 'issuing_body' => 'WHIP']);
            $certificate->takeFromWhip($whip, 'verified')->fill(['scope_note' => $whip->scopeNote]);
            $certificate->forceFill(['status' => CertificateStatus::Approved, 'submitted_at' => now(), 'reviewed_at' => now(), 'review_note' => null])->save();
            $certificate->bindProducts([...$certificate->products()->pluck('id'), ...($certificate->whip_unmatched_product_ids ?? [])], $whip);
        });
        $this->dispatch('toast', message: __('Certificate linked from WHIP.'));
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->creating = true;
        $this->registrationNo = (string) $this->currentStore()->registration_no;
    }

    /** Renew or correct an existing certificate — same row, back through review. */
    public function edit(int $id): void
    {
        $certificate = $this->certificates()->findOrFail($id);

        $this->resetForm();
        $this->editing = $id;
        $this->registrationNo = (string) $this->currentStore()->registration_no;
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
            'registrationNo' => ['nullable', 'string', 'max:40'],
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

        $verified = false;
        $parked = collect();

        DB::transaction(function () use ($certificate, $data, &$verified, &$parked) {
            $store = $this->currentStore();

            // The registration number is self-declared the first time and then
            // locked to the store. It is still only a claim until an admin
            // confirms it against the SSM document (registration_verified_at):
            // otherwise a seller could type another company's number and its
            // WHIP certificate number and be approved at once.
            if (blank($store->registration_no) && filled($data['registrationNo'])) {
                $store->forceFill(['registration_no' => trim($data['registrationNo'])])->save();
            }

            // WHIP is asked once, here. Verified = certified, in date, and the
            // same company registration number; anything else is manual review.
            [$answer, $whip] = app(WhipDirectory::class)->check($data['number'], $store->registration_no);
            $verified = $answer === 'verified' && $store->registration_verified_at !== null;

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
                'source' => 'manual',
                'whip_application_id' => null,
                'whip_synced_at' => null,
                'whip_answer' => $answer,
            ]);

            if ($verified) {
                $certificate->takeFromWhip($whip, $answer)->forceFill([
                    'status' => CertificateStatus::Approved,
                    'reviewed_at' => now(),
                ]);
            }

            $certificate->save();

            if ($this->document !== null) {
                $certificate->addMedia($this->document->getRealPath())
                    ->usingFileName('certificate-'.$certificate->number.'.'.$this->document->getClientOriginalExtension())
                    ->toMediaCollection('document');
            }

            // Only on a WHIP-verified row does WHIP decide which ticked products badge.
            $parked = $certificate->bindProducts($data['covered'], $verified ? $whip : null);

            HalalCertificateEvent::create([
                'halal_certificate_id' => $certificate->id,
                'occurred_on' => DisplayTime::today()->toDateString(),
                'summary' => $verified
                    ? __('Verified with WHIP')
                    : ($certificate->wasRecentlyCreated
                        ? __('Submitted for review')
                        : __('Renewal submitted for review')),
            ]);
        });

        $this->resetForm();
        $this->dispatch('toast', message: $verified
            ? __('Verified with WHIP. Your halal badge is live.')
            : __('Sent for review. Your products stay on sale while we check it.'));
        $parked->isNotEmpty() && $this->dispatch('toast', message: __('Not on your WHIP certificate, waiting for admin: :names', ['names' => $parked->implode(', ')]));
    }

    private function resetForm(): void
    {
        $this->reset(['editing', 'creating', 'number', 'issuingBody', 'holderName', 'scheme',
            'scopeNote', 'registrationNo', 'validFrom', 'validTo', 'facility', 'dedicatedFacility',
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
            'importCategories' => Category::active()->whereDoesntHave('children', fn ($q) => $q->active())->orderBy('id')->get(),
        ])->title(__('Halal certificates'));
    }
}
