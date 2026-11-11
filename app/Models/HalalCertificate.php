<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use App\Services\Whip\WhipCertificate;
use App\Support\DisplayTime;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A halal certificate as issued by a recognised body.
 *
 * One certificate belongs to a seller and covers many SKUs. That relationship
 * is the point of the whole marketplace: a badge that sits on the shop tells a
 * buyer nothing about the item in their basket, so the certificate is bound to
 * the products its scope actually names.
 */
class HalalCertificate extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'store_id', 'number', 'issuing_body', 'issuing_body_name', 'holder_name',
        'scheme', 'scope_note', 'valid_from', 'valid_to', 'facility',
        'dedicated_facility', 'export_paperwork',
    ];
    // source, whip_* are NOT fillable: only the WHIP check may set them.

    /**
     * Fails closed, and matches the column default the migration lands on:
     * anything created without an explicit status is a CLAIM awaiting review,
     * never evidence. (Existing rows were backfilled to approved by step 1 of
     * that migration; this governs new ones.)
     *
     * @var array<string, string>
     */
    protected $attributes = ['status' => CertificateStatus::Pending->value];

    /*
     * status, reviewed_at, reviewed_by and review_note are deliberately NOT
     * fillable: approval is the whole trust proposition, so it moves only
     * through the explicit writes in the admin review screen. submitted_at is
     * out too — it is stamped by the submission, not supplied with it.
     */

    /**
     * How long before expiry a seller gets nudged to renew.
     *
     * 90 days, not 60: JAKIM requires a renewal application at least three
     * months before the certificate lapses, so a 60-day nudge landed after the
     * window to act had already closed. Certificate terms are 1 year
     * (abattoirs), 2 (food premises and F&B products) or 3 (logistics,
     * cosmetics, pharma), with an optional 5-year term for long-standing
     * holders — so this is a fixed lead time, never a fraction of the term.
     */
    public const RENEWAL_WINDOW_DAYS = 90;

    /** The bodies this marketplace recognises, with the prefix each number carries. */
    public const BODIES = [
        'JAKIM' => ['prefix' => 'MY-JKM', 'name' => 'Department of Islamic Development Malaysia'],
        'MUIS' => ['prefix' => 'SG-MUIS', 'name' => 'Islamic Religious Council of Singapore'],
        'BPJPH' => ['prefix' => 'ID-BPJPH', 'name' => 'Halal Product Assurance Agency, Indonesia'],
        'ESMA' => ['prefix' => 'AE-ESMA', 'name' => 'Emirates Authority for Standardization and Metrology'],
    ];

    protected function casts(): array
    {
        return [
            'whip_synced_at' => 'datetime',
            'whip_unmatched_product_ids' => 'array',
            'whip_allowed_product_ids' => 'array',
            'status' => CertificateStatus::class,
            'valid_from' => 'date',
            'valid_to' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'dedicated_facility' => 'boolean',
            'export_paperwork' => 'boolean',
        ];
    }

    /**
     * The certificate scan. PRIVATE disk, exactly like the KYC documents in
     * StoreDocument: an uploaded certificate carries the holder's registered
     * address and signatures.
     *
     * ⚠ useDisk('local') is mandatory, not stylistic. No config/media-library.php
     * is published and MEDIA_DISK is unset, so Spatie's default is the
     * web-served PUBLIC disk — omitting this puts scans on a guessable URL.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('document')->singleFile()->useDisk('local');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', CertificateStatus::Approved);
    }

    #[Scope]
    protected function awaitingReview(Builder $query): void
    {
        $query->where('status', CertificateStatus::Pending);
    }

    /**
     * The public register lookup, one owner for the web page and the API: the
     * number as printed (spaces and case ignored), approved certificates only
     * (a merely submitted one is a claim, not a record), with what the register shows.
     */
    public static function forRegister(string $number): ?self
    {
        $query = strtoupper(preg_replace('/\s+/', '', $number));

        return $query === '' ? null : static::query()
            ->approved()
            ->with(['events', 'store'])
            ->withCount('products')
            ->where('number', $query)
            ->first();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(HalalCertificateEvent::class)->orderByDesc('occurred_on');
    }

    /**
     * Live means issued, not yet expired. Checked against the date, never a flag.
     *
     * Deliberately still a pure DATE question — Product::halalVerdict() asks
     * isApproved() separately. Folding review state in here would change the
     * meaning of a method whose whole job is the two date bounds, and the
     * register renders "expires in N days" off the same pair.
     */
    public function isValid(): bool
    {
        $today = DisplayTime::today();

        return $this->valid_from->lte($today) && $this->valid_to->gte($today);
    }

    /**
     * Bind this store's products $ids to the certificate and unbind the rest.
     * Given WHIP's certificate (source=whip), only products WHIP lists, or an
     * admin allowed, bind; the others are parked in whip_unmatched_product_ids.
     * Without it (manual) every id binds. Returns the parked product names.
     *
     * @return Collection<int, string>
     */
    public function bindProducts(array $ids, ?WhipCertificate $whip = null): Collection
    {
        $products = Product::query()->where('store_id', $this->store_id)->whereIn('id', $ids)
            ->with('variants:id,product_id,sku')->get();
        $allowed = $this->whip_allowed_product_ids ?? [];

        [$bind, $parked] = $whip === null ? [$products, collect()]
            : $products->partition(fn (Product $p) => in_array($p->id, $allowed, true) || $whip->covers($p->name, $p->variants->pluck('sku')));

        Product::query()->where('halal_certificate_id', $this->id)->whereNotIn('id', $bind->modelKeys())
            ->update(['halal_certificate_id' => null]);
        Product::query()->whereIn('id', $bind->modelKeys())->update([
            'halal_certificate_id' => $this->id,
            'halal_cert_number' => $this->number,
            'halal_cert_expiry' => $this->valid_to,
        ]);
        $this->forceFill(['whip_unmatched_product_ids' => $whip === null ? null : array_values($parked->modelKeys())])->save();

        return $parked->pluck('name');
    }

    /** Ticked products WHIP does not list, waiting for an admin. */
    public function unmatchedProducts(): Collection
    {
        return Product::query()->whereIn('id', $this->whip_unmatched_product_ids ?? [])->get(['id', 'name']);
    }

    /** Copy what WHIP holds onto this row and stamp the check. Caller saves. */
    public function takeFromWhip(WhipCertificate $whip, string $answer): static
    {
        $this->fill([
            'holder_name' => $whip->companyName ?: $this->holder_name,
            'issuing_body_name' => $whip->bodyName ?: $this->issuing_body_name,
            'valid_from' => $whip->validFrom ?: $this->valid_from,
            'valid_to' => $whip->validTo ?: $this->valid_to,
        ]);

        return $this->forceFill([
            'source' => 'whip',
            'whip_application_id' => $whip->applicationId,
            'whip_synced_at' => now(),
            'whip_answer' => $answer,
        ]);
    }

    /**
     * A WHIP-sourced certificate is trusted for 24 h after its last good sync.
     * Older (or never synced) and the badge fails closed; nothing is delisted.
     */
    public function isWhipStale(): bool
    {
        return $this->source === 'whip'
            && ($this->whip_synced_at === null || $this->whip_synced_at->lt(now()->subHours(24)));
    }

    /** One plain line for the admin screen: what WHIP said at the last check. */
    public function whipNote(): ?string
    {
        return match ($this->whip_answer) {
            'verified' => __('WHIP: certified and in date for registration number :reg. Check it against the store\'s SSM document before approving.', ['reg' => $this->store?->registration_no]),
            'expired' => __('WHIP: found, but the certificate has expired.'),
            'not_certified' => __('WHIP: found, but not certified (or not yet in force).'),
            'mismatch' => __('WHIP: found, but its company registration number differs from this store\'s.'),
            'ambiguous' => __('WHIP: several records share this number; check by hand.'),
            'incomplete' => __('WHIP: found and certified, but its dates are incomplete.'),
            'not_found' => __('WHIP: no certificate with this number.'),
            'unavailable' => __('WHIP: could not be reached, not checked.'),
            default => null,
        };
    }

    /** A submitted certificate is a claim; an approved one is evidence. */
    public function isApproved(): bool
    {
        return $this->status === CertificateStatus::Approved;
    }

    /** Negative once it has lapsed, which is what the UI needs to say so. */
    public function daysRemaining(): int
    {
        return (int) DisplayTime::today()->diffInDays($this->valid_to, false);
    }

    /**
     * Inside the renewal window the seller should be nudged in. */
    public function isExpiringSoon(?int $withinDays = null): bool
    {
        $left = $this->daysRemaining();

        return $left >= 0 && $left <= ($withinDays ?? self::RENEWAL_WINDOW_DAYS);
    }

    /** Resolve the issuing body from a certificate number's prefix. */
    public static function bodyFromNumber(string $number): ?string
    {
        foreach (self::BODIES as $body => $meta) {
            if (str_starts_with($number, $meta['prefix'].'-')) {
                return $body;
            }
        }

        return null;
    }
}
