<?php

namespace App\Console\Commands;

use App\Enums\ProductStatus;
use App\Models\HalalCertificate;
use App\Notifications\HalalCertificateWatch;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Halal certificate expiry watch — the last item on /welcome still chipped
 * "In build" (docs: the certificate layer shipped everything except this).
 *
 * Three sweeps, daily:
 *   1. LAPSED   — a term has ended, so its products come off the storefront.
 *   2. RESTORED — the term is valid again, so the products the watch took down
 *                 go back up. Only those: `halal_delisted_at` records which
 *                 ones this job delisted, so a product the SELLER withdrew is
 *                 never resurrected by someone renewing a certificate.
 *   3. NUDGE    — inside the renewal window, warn once.
 *
 * The whole point is that a certificate is the trust claim this marketplace
 * sells. A product still on sale under an expired certificate is the single
 * worst thing this catalogue can do, and until now nothing checked.
 *
 * Idempotent — safe to run repeatedly; each sweep only touches rows not already
 * in the target state.
 */
class WatchCertificateExpiry extends Command
{
    protected $signature = 'certificates:watch-expiry {--days= : Renewal window in days (default: config)}';

    protected $description = 'Delist products under expired halal certificates, restore renewed ones, and nudge sellers before expiry';

    public function handle(): int
    {
        // The window lives on the model as RENEWAL_WINDOW_DAYS, so the nudge
        // and the "expiring soon" badge cannot disagree. It used to say that
        // while hardcoding 60 here as well — two places claiming to be one.
        $window = (int) ($this->option('days') ?: HalalCertificate::RENEWAL_WINDOW_DAYS);
        $today = now()->startOfDay();

        $lapsed = $this->sweepLapsed($today);
        $restored = $this->sweepRestored($today);
        $nudged = $this->sweepRenewalNudge($today, $window);

        $this->info("Expiry watch: {$lapsed} product(s) delisted, {$restored} restored, {$nudged} seller(s) nudged.");

        return self::SUCCESS;
    }

    /** Terms that have ended: take their live products down. */
    private function sweepLapsed(Carbon $today): int
    {
        $delisted = 0;

        HalalCertificate::query()
            ->whereDate('valid_to', '<', $today)
            ->with('store.user')
            ->chunkById(100, function ($certificates) use (&$delisted) {
                foreach ($certificates as $certificate) {
                    $count = DB::transaction(function () use ($certificate) {
                        // Only Live products, and only ones not already marked
                        // — so a re-run does not re-stamp or re-notify.
                        $ids = $certificate->products()
                            ->where('status', ProductStatus::Live)
                            ->pluck('id');

                        if ($ids->isEmpty()) {
                            return 0;
                        }

                        $certificate->products()
                            ->whereIn('id', $ids)
                            ->update([
                                'status' => ProductStatus::Delisted,
                                'halal_delisted_at' => now(),
                            ]);

                        return $ids->count();
                    });

                    if ($count > 0) {
                        $delisted += $count;
                        $certificate->store?->user?->notify(
                            new HalalCertificateWatch($certificate, 'lapsed', $count),
                        );
                    }
                }
            });

        return $delisted;
    }

    /** Renewed (or corrected) terms: put back exactly what the watch took. */
    private function sweepRestored(Carbon $today): int
    {
        $restored = 0;

        HalalCertificate::query()
            // approved() added with H-6: a certificate the seller has merely
            // re-submitted must not restore listings on its own say-so. The
            // grace window in ProductPublishPolicy covers the seller during
            // review; this sweep is what makes it permanent, and it waits.
            ->approved()
            ->whereDate('valid_from', '<=', $today)
            ->whereDate('valid_to', '>=', $today)
            ->chunkById(100, function ($certificates) use (&$restored) {
                foreach ($certificates as $certificate) {
                    $count = $certificate->products()
                        ->whereNotNull('halal_delisted_at')
                        ->where('status', ProductStatus::Delisted)
                        ->update([
                            'status' => ProductStatus::Live,
                            'halal_delisted_at' => null,
                        ]);

                    // A renewed term should be able to warn again next time.
                    if ($certificate->renewal_notified_at !== null && ! $certificate->isExpiringSoon()) {
                        $certificate->forceFill(['renewal_notified_at' => null])->save();
                    }

                    $restored += $count;
                }
            });

        return $restored;
    }

    /** Inside the renewal window and not yet warned: warn once. */
    private function sweepRenewalNudge(Carbon $today, int $window): int
    {
        $nudged = 0;

        HalalCertificate::query()
            ->whereNull('renewal_notified_at')
            ->whereDate('valid_to', '>=', $today)
            ->whereDate('valid_to', '<=', $today->copy()->addDays($window))
            ->with('store.user')
            ->chunkById(100, function ($certificates) use (&$nudged) {
                foreach ($certificates as $certificate) {
                    $user = $certificate->store?->user;

                    // Stamp regardless of whether a recipient exists: an
                    // ownerless store must not make this query re-run forever.
                    $certificate->forceFill(['renewal_notified_at' => now()])->save();

                    if ($user === null) {
                        continue;
                    }

                    $user->notify(new HalalCertificateWatch(
                        $certificate,
                        'expiring',
                        $certificate->products()->where('status', ProductStatus::Live)->count(),
                    ));

                    $nudged++;
                }
            });

        return $nudged;
    }
}
