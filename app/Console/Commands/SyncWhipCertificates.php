<?php

namespace App\Console\Commands;

use App\Enums\CertificateStatus;
use App\Models\HalalCertificate;
use App\Models\HalalCertificateEvent;
use App\Models\Store;
use App\Services\Whip\WhipDirectory;
use App\Support\DisplayTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps WHIP-sourced certificates in step with WHIP (read-only).
 *
 * WHIP is asked about EVERY source=whip row first; only if all answers came
 * back does anything change, so a dead connection changes nothing. Dates and
 * status then follow WHIP. Expired is a date state, so the existing expiry
 * watch is run afterwards to delist exactly as it does daily. Idempotent.
 */
class SyncWhipCertificates extends Command
{
    protected $signature = 'certificates:sync-whip';

    protected $description = 'Re-check WHIP-sourced halal certificates against WHIP and follow its status and dates';

    public function handle(WhipDirectory $whip): int
    {
        return DB::transaction(function () use ($whip) {
            // Same lock order as seller link/import: take local locks BEFORE asking WHIP.
            $storeIds = HalalCertificate::where('source', 'whip')->pluck('store_id');
            $stores = Store::query()->where(fn ($q) => $q->whereIn('id', $storeIds)->orWhereNotNull('whip_application_id'))
                ->orderBy('id')->lockForUpdate()->get();
            $certificates = HalalCertificate::query()->where('source', 'whip')->with('store')->orderBy('id')->lockForUpdate()->get();

            try {
                $answers = $certificates->map(function (HalalCertificate $c) use ($whip) {
                    $found = $whip->lookup($c->number);

                    return [$c, $c->store?->registration_verified_at === null ? 'mismatch' : ($found ? $found->answerFor($c->store->registration_no) : 'not_found'), $found, false];
                })->keyBy(fn ($row) => strtoupper(trim($row[0]->number)));

                foreach ($stores as $store) {
                    if ($store->whip_application_id === null || $store->registration_verified_at === null) {
                        continue;
                    }
                    foreach ($whip->forCompany((string) $store->registration_no) as $found) {
                        if ($found->applicationId !== (int) $store->whip_application_id || $found->answerFor($store->registration_no) !== 'verified') {
                            continue;
                        }
                        $certificate = HalalCertificate::where('number', $found->certificateNo)->lockForUpdate()->first();
                        if ($certificate !== null && $certificate->store_id !== $store->id) {
                            continue;
                        }
                        if ($certificate === null && blank($found->companyName)) {
                            continue;
                        }
                        $certificate ??= new HalalCertificate(['store_id' => $store->id, 'number' => $found->certificateNo, 'issuing_body' => 'WHIP']);
                        $answers->put(strtoupper(trim($found->certificateNo)), [$certificate, 'verified', $found, true]);
                    }
                }
            } catch (Throwable $e) {
                Log::error('certificates:sync-whip: WHIP unreachable, nothing changed', ['exception' => $e::class]);
                $this->error('WHIP unreachable; nothing changed.');

                return self::FAILURE;
            }

            $changed = 0;

            foreach ($answers as [$cert, $answer, $found, $followed]) {
                $before = $cert->whip_answer;

                if ($found !== null && in_array($answer, ['verified', 'expired'], true)) {
                    $cert->takeFromWhip($found, $answer)->fill(['scope_note' => $found->scopeNote]);
                    // verified: WHIP vouches again (also restores a row an earlier sync rejected).
                    $answer === 'verified' && $cert->forceFill(['status' => CertificateStatus::Approved, 'review_note' => null]);
                } else {
                    // Gone, not certified, or no longer this store's: WHIP no longer vouches.
                    $cert->forceFill([
                        'status' => CertificateStatus::Rejected,
                        'review_note' => __('WHIP no longer lists this certificate as certified for your company.'),
                        'whip_synced_at' => now(),
                        'whip_answer' => $answer,
                    ]);
                }

                $cert->save();

                // WHIP's product list may have changed: re-apply it to what is bound or waiting.
                $ids = [...$cert->products()->pluck('id'), ...($cert->whip_unmatched_product_ids ?? [])];
                if ($followed) {
                    $matches = $cert->store->products()->with('variants:id,product_id,sku')->get()
                        ->filter(fn ($product) => $found->covers($product->name, $product->variants->pluck('sku')))->modelKeys();
                    $ids = [...$ids, ...$matches];
                }
                $found !== null && $cert->bindProducts($ids, $found);

                if ($before !== $answer) {
                    $changed++;
                    HalalCertificateEvent::create([
                        'halal_certificate_id' => $cert->id,
                        'occurred_on' => DisplayTime::today()->toDateString(),
                        'summary' => __('WHIP check: :answer', ['answer' => str_replace('_', ' ', $answer)]),
                    ]);
                }
            }

            // Same effects as the daily watch: lapsed terms come off sale.
            $this->call('certificates:watch-expiry');

            $this->info("WHIP sync: {$answers->count()} checked, {$changed} changed.");

            return self::SUCCESS;
        });
    }
}
