<?php

namespace App\Services\Whip;

/** What WHIP says about one certificate number. Read-only snapshot. */
final readonly class WhipCertificate
{
    public const ACTIVE = 'active';

    public const EXPIRED = 'expired';

    public const NOT_CERTIFIED = 'not_certified';

    public const AMBIGUOUS = 'ambiguous';

    /**
     * @param  'active'|'expired'|'not_certified'|'ambiguous'  $status
     * @param  list<array{name: string, brand: ?string, barcode: ?string}>  $products
     */
    public function __construct(
        public string $status,
        public string $certificateNo,
        public ?string $validFrom,
        public ?string $validTo,
        public ?string $companyName,
        public ?string $registrationNumber,
        public ?string $bodyName,
        public array $products,
        public int $applicationId,
        public ?string $scopeNote = null,
    ) {}

    /** Company registration numbers compare upper-case with spaces and dashes removed. */
    public static function normaliseRegistration(?string $no): string
    {
        return preg_replace('/[\s-]+/', '', strtoupper((string) $no));
    }

    /**
     * The one verdict both the seller submit and the sync act on.
     *
     * @return 'verified'|'expired'|'not_certified'|'ambiguous'|'mismatch'|'incomplete'
     */
    public function answerFor(?string $storeRegistrationNo): string
    {
        if ($this->status !== self::ACTIVE) {
            return $this->status;
        }

        $theirs = self::normaliseRegistration($this->registrationNumber);

        if ($theirs === '' || $theirs !== self::normaliseRegistration($storeRegistrationNo)) {
            return 'mismatch';
        }

        // HalalBizs rows need both dates; an open-ended WHIP date goes to a human.
        return $this->validFrom && $this->validTo ? 'verified' : 'incomplete';
    }

    /**
     * Is this seller product one of WHIP's? Exact only: a WHIP barcode equals
     * one of the product's variant SKUs, or the normalised name equals WHIP's
     * product name or "brand product name". No fuzzy matching.
     *
     * @param  iterable<string>  $skus
     */
    public function covers(string $name, iterable $skus): bool
    {
        $skus = collect($skus)->map(fn ($s) => strtolower(trim($s)))->filter()->all();
        $name = self::normaliseName($name);

        foreach ($this->products as $p) {
            if ($p['barcode'] && in_array(strtolower(trim($p['barcode'])), $skus, true)) {
                return true;
            }
            if ($name !== '' && in_array($name, [self::normaliseName($p['name']), self::normaliseName($p['brand'].' '.$p['name'])], true)) {
                return true;
            }
        }

        return false;
    }

    /** Lower-case, punctuation out, spaces collapsed. */
    private static function normaliseName(?string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^\p{L}\p{N}\s]+/u', '', mb_strtolower((string) $s))));
    }
}
