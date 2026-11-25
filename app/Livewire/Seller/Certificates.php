<?php

namespace App\Livewire\Seller;

use App\Enums\CertificateStatus;
use App\Livewire\Concerns\CurrentStore;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Services\ProductWriteService;
use App\Support\DisplayTime;
use App\Services\Whip\WhipDirectory;
use App\Settings\ModerationSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Livewire\Concerns\WithTable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
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
    use CurrentStore, WithTable;

    public const PER_PAGE = 15;

    /** Company registration (SSM) number: how WHIP ties a certificate to this seller. */
    public string $registrationNo = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $body = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: false)]
    public bool $expiring = false;

    #[Url(except: '')]
    public string $validFrom = '';

    #[Url(except: '')]
    public string $validTo = '';

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
        [$answer, $whip] = app(WhipDirectory::class)->check($certificate->number, $store->registration_no);
        $count = DB::transaction(function () use ($certificate, $store, $answer, $whip) {
            $store = $store->newQuery()->lockForUpdate()->findOrFail($store->id);
            $certificate = $this->certificates()->lockForUpdate()->findOrFail($certificate->id);
            abort_unless($certificate->source === 'whip' && $store->registration_verified_at !== null, 403);
            if ($answer !== 'verified' || $whip->answerFor($store->registration_no) !== 'verified'
                || $whip->certificateNo !== $certificate->number
                || $whip->applicationId !== (int) $certificate->whip_application_id) {
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
        abort_unless($store->registration_verified_at !== null && filled($store->registration_no), 403);
        [$answer, $whip] = app(WhipDirectory::class)->check($number, $store->registration_no);
        $approved = false;
        DB::transaction(function () use ($store, $number, $answer, $whip, &$approved) {
            $store = $store->newQuery()->lockForUpdate()->findOrFail($store->id);
            abort_unless($store->registration_verified_at !== null && filled($store->registration_no), 403);
            $certificate = HalalCertificate::whereRaw('UPPER(TRIM(number)) = ?', [strtoupper(trim($number))])->lockForUpdate()->first();
            abort_if($certificate !== null && $certificate->store_id !== $store->id, 403);
            if ($answer !== 'verified' || $whip->answerFor($store->registration_no) !== 'verified' || blank($whip->companyName)) {
                throw ValidationException::withMessages(['whip' => __('WHIP could not confirm this certificate. Upload it for manual review.')]);
            }
            $certificate ??= new HalalCertificate(['store_id' => $store->id, 'number' => $whip->certificateNo, 'issuing_body' => 'WHIP']);
            $certificate->takeFromWhip($whip, 'verified')->fill(['scope_note' => $whip->scopeNote]);
            // G247: switch OFF = the admin review queue, marked WHIP confirmed (whip_answer).
            // A relink never undoes an approval already given (same rule as the sync).
            $auto = app(ModerationSettings::class)->whip_auto_approve;
            $keep = ! $auto && $certificate->status === CertificateStatus::Approved;
            $approved = $auto || $keep;
            $certificate->forceFill(['submitted_at' => now(), 'review_note' => null] + ($keep ? [] : [
                'status' => $auto ? CertificateStatus::Approved : CertificateStatus::Pending,
                'reviewed_at' => $auto ? now() : null, 'reviewed_by' => null,
            ]))->save();
            $certificate->bindProducts([...$certificate->products()->pluck('id'), ...($certificate->whip_unmatched_product_ids ?? [])], $whip);
        });
        $this->dispatch('toast', message: $approved
            ? __('Certificate linked from WHIP.')
            : __('WHIP confirmed this certificate. Our team will check it before the halal badge shows.'));
    }

    private function certificates()
    {
        return $this->currentStore()->halalCertificates();
    }

    protected function tableFilters(): array
    {
        return ['status', 'body', 'source', 'expiring', 'validFrom', 'validTo'];
    }

    protected function tableSorts(): array
    {
        return ['number' => 'number', 'status' => 'status', 'valid_to' => 'valid_to', 'products' => 'products_count'];
    }

    protected function tableDefaultOrder(): array
    {
        return [];
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'body', 'source', 'expiring', 'validFrom', 'validTo');
        $this->resetPage();
    }

    private function results()
    {
        $query = HalalCertificate::query()->where('store_id', $this->currentStore()->id)->withCount('products')->with('products:id,halal_certificate_id')
            ->when(CertificateStatus::tryFrom($this->status), fn ($q, $status) => $q->where('status', $status))
            ->when(array_key_exists($this->body, HalalCertificate::BODIES), fn ($q) => $q->where('issuing_body', $this->body))
            ->when(in_array($this->source, ['whip', 'manual'], true), fn ($q) => $q->where('source', $this->source))
            ->when($this->expiring, fn ($q) => $q->where('status', CertificateStatus::Approved)
                ->whereBetween('valid_to', [DisplayTime::today()->toDateString(), DisplayTime::today()->addDays(HalalCertificate::RENEWAL_WINDOW_DAYS)->toDateString()]))
            ->when($this->validDate($this->validFrom), fn ($q, $d) => $q->whereDate('valid_to', '>=', $d))
            ->when($this->validDate($this->validTo), fn ($q, $d) => $q->whereDate('valid_to', '<=', $d))
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('number', 'like', '%'.trim($this->search).'%')
                ->orWhere('issuing_body', 'like', '%'.trim($this->search).'%')));

        return $this->sortTable($query)->paginate($this->tablePageSize());
    }

    /** Date inputs arrive as Y-m-d from the browser; ignore anything else. */
    private function validDate(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    public function render()
    {
        $certificates = $this->results();

        return view('livewire.seller.certificates', [
            'certificates' => $certificates,
            'hasWhip' => $this->certificates()->reorder()->where('source', 'whip')->exists(),
            'hasAny' => $this->certificates()->reorder()->exists(),
            'bodies' => HalalCertificate::BODIES,
            'importCategories' => Category::active()->whereDoesntHave('children', fn ($q) => $q->active())->orderBy('id')->get(),
        ])->title(__('Halal certificates'));
    }
}
