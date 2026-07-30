<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A halal certificate as issued by a recognised body.
 *
 * One certificate belongs to a seller and covers many SKUs. That relationship
 * is the point of the whole marketplace: a badge that sits on the shop tells a
 * buyer nothing about the item in their basket, so the certificate is bound to
 * the products its scope actually names.
 */
class HalalCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'number', 'issuing_body', 'issuing_body_name', 'holder_name',
        'scheme', 'scope_note', 'valid_from', 'valid_to', 'facility',
        'dedicated_facility', 'export_paperwork',
    ];

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
            'valid_from' => 'date',
            'valid_to' => 'date',
            'dedicated_facility' => 'boolean',
            'export_paperwork' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(HalalCertificateEvent::class)->orderByDesc('occurred_on');
    }

    /** Live means issued, not yet expired. Checked against the date, never a flag. */
    public function isValid(): bool
    {
        $today = now()->startOfDay();

        return $this->valid_from->lte($today) && $this->valid_to->gte($today);
    }

    /** Negative once it has lapsed, which is what the UI needs to say so. */
    public function daysRemaining(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->valid_to, false);
    }

    /** Inside the renewal window the seller should be nudged in. */
    public function isExpiringSoon(int $withinDays = 60): bool
    {
        $left = $this->daysRemaining();

        return $left >= 0 && $left <= $withinDays;
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
