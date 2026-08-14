<?php

namespace App\Services;

use App\Models\Product;

/**
 * The one place that answers "may this product go live?" (audit H-6).
 *
 * Gate food, badge the rest: a product in a category that requires halal
 * certification cannot be listed without an approved certificate covering it.
 * Everything else is unaffected — a prayer mat needs no certificate and must
 * not be blocked by one.
 *
 * WHY A POLICY AND NOT AN `IF` IN THE FORM. Four places write
 * ProductStatus::Live today and they already disagree: Seller\Products\Form
 * and Seller\Products\Index each read ModerationSettings separately, while
 * Admin\Catalog\Moderation and WatchCertificateExpiry's restore sweep write it
 * with no check at all. A rule enforced at one call site is not a rule — it is
 * a rule at one call site, and the other three keep the bug.
 *
 * THREE VALVES, all so that OUR latency never costs a seller a live listing:
 *
 *  1. halal_gate_enforced_from — until that date the gate reports, it does not
 *     block. Switching this on with a date already past would dark the
 *     catalogue at deploy, which is the exact failure the whole H-6 sequence
 *     has been avoiding.
 *  2. A submitted certificate buys REVIEW_GRACE_DAYS. A seller who has done
 *     their part is not punished for our review queue.
 *  3. Grace applies to a RENEWAL too, which is what makes the 90-day nudge
 *     honest: renewing early must not cost the badge, or nobody renews early.
 */
class ProductPublishPolicy
{
    /**
     * Days a submitted certificate holds the line while we review it.
     *
     * Haze's number. Long enough to cover a normal review, short enough that
     * an abandoned submission cannot hold a listing open indefinitely.
     */
    public const REVIEW_GRACE_DAYS = 14;

    /** May this product be listed right now? */
    public function allows(Product $product): bool
    {
        return $this->blockedReason($product) === null;
    }

    /**
     * Why not, in the seller's words — or null when it may go live.
     *
     * Returns the reason rather than a bare bool so every caller shows the
     * same explanation instead of inventing its own.
     */
    public function blockedReason(Product $product): ?string
    {
        if (! $this->enforcing() || ! $this->categoryRequiresCertificate($product)) {
            return null;
        }

        $certificate = $product->halalCertificate;

        if ($certificate === null) {
            return __('This category needs a halal certificate. Register yours under Halal certificates, then tick this product.');
        }

        if ($certificate->isApproved() && $certificate->isValid()) {
            return null;
        }

        // Valve 2 and 3: submitted and inside the grace window, so it stands
        // while we review — first submission or renewal alike.
        if ($certificate->submitted_at !== null
            && $certificate->submitted_at->gt(now()->subDays(self::REVIEW_GRACE_DAYS))) {
            return null;
        }

        if (! $certificate->isValid()) {
            return __('The halal certificate covering this product has expired. Renew it to keep the listing live.');
        }

        return __('The halal certificate covering this product is still being checked.');
    }

    /**
     * Whether the gate blocks yet.
     *
     * Unset or future = report only. This is the switch that lets the rule ship
     * before the catalogue is ready for it.
     */
    public function enforcing(): bool
    {
        $from = config('halal.gate_enforced_from');

        return $from !== null && $from !== '' && now()->gte(now()->parse($from));
    }

    /** Walks the category tree, nearest ancestor wins. */
    private function categoryRequiresCertificate(Product $product): bool
    {
        return $product->category?->requiresHalalCertificate() === true;
    }
}
