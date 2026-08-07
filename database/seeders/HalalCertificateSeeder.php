<?php

namespace Database\Seeders;

use App\Models\HalalCertificate;
use App\Models\HalalCertificateEvent;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Builds the certificate records the register screen reads, from the products
 * already seeded by HalalCatalogueSeeder.
 *
 * ONE CERTIFICATE PER SELLER PER ISSUING BODY. That mirrors reality — a body
 * certifies a facility and a scope, and every SKU made under it is covered by
 * the same number — and it is what makes "the N covered SKUs" on the register a
 * real count rather than a decoration.
 *
 * Run AFTER HalalCatalogueSeeder. Idempotent: keyed on the certificate number.
 */
class HalalCertificateSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = json_decode(File::get(database_path('seeders/data/halalbizs-catalogue.json')), true, 512, JSON_THROW_ON_ERROR);
        $legalNames = collect($catalogue['stores'])->keyBy(fn (array $s): string => $s['name']);

        // Group the seeded products by (store, issuing body) — the certificate's
        // natural grain.
        $groups = Product::query()
            ->whereNotNull('halal_cert_number')
            ->with('store')
            ->get()
            ->groupBy(fn (Product $p): string => $p->store_id.'|'.(HalalCertificate::bodyFromNumber($p->halal_cert_number) ?? 'NONE'))
            ->filter(fn ($_, string $key): bool => ! str_ends_with($key, '|NONE'));

        $made = 0;
        $bound = 0;

        foreach ($groups as $key => $products) {
            [$storeId, $body] = explode('|', $key);
            $store = $products->first()->store;

            if (! $store instanceof Store) {
                continue;
            }

            // The number the register is looked up by: reuse the first product's,
            // so the number already printed on listings resolves.
            $number = $products->first()->halal_cert_number;
            $meta = HalalCertificate::BODIES[$body];
            $legal = $legalNames[$store->name]['legal_name'] ?? $store->name;
            $since = (int) ($legalNames[$store->name]['since'] ?? 2020);

            // Validity is derived from the products' own expiry so the record and
            // the listings cannot disagree on the END date.
            $validTo = $products->max('halal_cert_expiry') ?? now()->addYear();

            // ⚠ The start date must be CLAMPED to today. Deriving it as
            // `$validTo->subYears(2)` guaranteed agreement on the end and
            // invented a beginning nobody checked: product expiries sit ~2.4
            // years out, so a two-year term started in the FUTURE. That put 17
            // of 24 seeded certificates in a not-yet-in-force state, which the
            // register correctly reported as NOT VALID while the listings
            // showed a green tick (2026-08-07). A demo certificate that is not
            // yet in force is not a useful demo of anything.
            $validFrom = min((clone $validTo)->subYears(2), now()->startOfDay()->subDay());

            $certificate = HalalCertificate::updateOrCreate(
                ['number' => $number],
                [
                    'store_id' => $store->id,
                    'issuing_body' => $body,
                    'issuing_body_name' => $meta['name'],
                    'holder_name' => $legal,
                    'scheme' => $body === 'JAKIM' ? 'MS 1500:2019' : null,
                    'scope_note' => $this->scopeNote($products),
                    'valid_from' => $validFrom,
                    'valid_to' => $validTo,
                    'facility' => 'Plant '.strtoupper(substr($body, 0, 2)).'-'.str_pad((string) ($store->id % 90 + 10), 2, '0', STR_PAD_LEFT).', '.($store->state ?: 'Malaysia'),
                    // Deterministic from the store id so a re-run does not shuffle
                    // what a buyer filtered on last time.
                    'dedicated_facility' => $store->id % 3 !== 0,
                    'export_paperwork' => $store->id % 2 === 0,
                ],
            );
            $made++;

            // "Audited seller since" must read the catalogue's year, not the
            // date the seeder happened to run.
            if ($store->approved_at?->year !== $since) {
                $store->forceFill(['approved_at' => now()->setDate($since, 1, 15)->startOfDay()])->saveQuietly();
            }

            $this->seedTrail($certificate, $since);

            // Bind every product in the group, and give each a batch of its own:
            // the certificate covers the scope, the batch identifies the item.
            foreach ($products as $product) {
                $product->forceFill([
                    'halal_certificate_id' => $certificate->id,
                    // ⚠ The covered SKUs must carry the CERTIFICATE's number and
                    // expiry, not the per-product ones the catalogue seeder
                    // generated. One certificate covers many SKUs, so they share
                    // its number by definition — leaving the old values made the
                    // product page show one number in the certificate panel and a
                    // different one in the traceability table.
                    'halal_cert_number' => $certificate->number,
                    'halal_cert_expiry' => $certificate->valid_to,
                    'halal_batch_code' => 'B-'.$validFrom->format('Y').'-'.substr((string) crc32($product->slug), 0, 4),
                    'halal_packed_on' => now()->subDays(crc32($product->slug) % 120)->startOfDay(),
                ])->saveQuietly();
                $bound++;
            }
        }

        $this->command?->info("Certificates: {$made} · products bound: {$bound}");
    }

    /** A scope line built from what the certificate actually covers. */
    private function scopeNote(Collection $products): string
    {
        $leaves = $products->map(fn (Product $p) => $p->category?->getTranslation('name', 'en'))
            ->filter()->unique()->take(3)->implode(', ');

        return $leaves !== ''
            ? mb_strtolower($leaves).' ('.$products->count().' SKUs)'
            : $products->count().' SKUs';
    }

    /** The audit trail. Dates are derived from the certificate's own validity so
     *  the trail cannot contradict the record above it. */
    private function seedTrail(HalalCertificate $certificate, int $since): void
    {
        if ($certificate->events()->exists()) {
            return;
        }

        $trail = [
            [$certificate->valid_from->copy()->subMonths(2), __('Application filed · dedicated halal line commissioned')],
            [$certificate->valid_from, __('Certificate issued after initial facility audit')],
            [$certificate->valid_from->copy()->addMonths(11), __('Scope extended · additional SKUs added to annex A')],
            [$certificate->valid_to->copy()->subMonths(9), __('Surveillance audit passed · no non-conformances raised')],
        ];

        foreach ($trail as [$date, $summary]) {
            if ($date->isFuture()) {
                continue;
            }

            HalalCertificateEvent::create([
                'halal_certificate_id' => $certificate->id,
                'occurred_on' => $date,
                'summary' => $summary,
            ]);
        }
    }
}
