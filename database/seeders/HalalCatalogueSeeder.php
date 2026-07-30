<?php

namespace Database\Seeders;

use App\Enums\HalalStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The real halal-grocery catalogue: 19 seller accounts, 166 SKUs, real packshots.
 *
 * Replaces the factory demo data (Latin names, GD colour-block images) that the
 * revamp made look absurd next to the new design. Data lives in
 * database/seeders/data/halalbizs-catalogue.json -- 42 items measured off the
 * reference design concept, 124 authored in the same idiom.
 *
 * IDEMPOTENT: keyed on store slug and product slug, so re-running updates in
 * place rather than duplicating. Safe to run after the packshot batch finishes to
 * fill in images that were missing on an earlier pass.
 *
 * Images are looked up on disk and SKIPPED when absent -- a product with no
 * packshot still seeds correctly and picks one up on the next run.
 */
class HalalCatalogueSeeder extends Seeder
{
    /** Where the two packshot sets live. Neither is inside the repo: the
     *  reference set ships with the design concept, the generated set is written
     *  by the Codex batch. */
    private const IMAGE_ROOTS = [
        'reference' => 'Downloads/Halal Bizs E-commerce Revamp/assets/products',
        'generated' => 'Downloads/halalbizs-packshots',
    ];

    public function run(): void
    {
        $path = database_path('seeders/data/halalbizs-catalogue.json');

        if (! File::exists($path)) {
            $this->command?->error("Catalogue not found at {$path}");

            return;
        }

        $data = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        $leaves = Category::query()
            ->whereNotNull('parent_id')
            ->get()
            ->keyBy(fn (Category $c): string => $c->getTranslation('name', 'en'));

        $stores = $this->seedStores($data['stores']);

        $seeded = 0;
        $withImage = 0;
        $missingImages = [];

        foreach ($data['products'] as $row) {
            $store = $stores[$row['brand']] ?? null;
            $leaf = $leaves[$row['leaf']] ?? null;

            if (! $store || ! $leaf) {
                $this->command?->warn("Skipped {$row['name_ms']}: unknown ".($store ? 'leaf '.$row['leaf'] : 'brand '.$row['brand']));

                continue;
            }

            $product = $this->seedProduct($row, $store, $leaf);
            $seeded++;

            if ($this->attachPackshot($product, $row['image'])) {
                $withImage++;
            } else {
                $missingImages[] = $row['image'];
            }
        }

        $this->command?->info("Stores: {$stores->count()} · products: {$seeded} · with packshot: {$withImage}");

        if ($missingImages !== []) {
            $this->command?->warn(count($missingImages).' packshots not on disk yet (re-run this seeder once the batch finishes)');
        }
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function seedStores(array $rows): Collection
    {
        return collect($rows)->mapWithKeys(function (array $row): array {
            $slug = Str::slug($row['name']);
            // One login per seller, so every store can actually be signed into.
            $email = Str::slug($row['brand'], '.').'@halalbizs.test';

            $user = User::withTrashed()->firstOrNew(['email' => $email]);
            $user->fill([
                'name' => $row['name'],
                'password' => bcrypt('password'),
                'email_verified_at' => $user->email_verified_at ?? now(),
                'deleted_at' => null,
            ])->save();

            if (! $user->hasRole('seller')) {
                $user->assignRole('seller');
            }

            // Key on USER, not on a slug derived from the display name:
            // stores.user_id is unique, so renaming a store must update the
            // existing row rather than try to insert a second one for the same
            // seller (which is exactly what a slug key did when the display
            // names were shortened to the brand).
            $store = Store::withTrashed()->firstOrNew(['user_id' => $user->id]);
            $store->fill([
                'slug' => $slug,
                'name' => $row['name'],
                // Display name is the short brand (as the reference's cards and
                // storefront show it); the legal entity lives in the description.
                'description' => ($row['legal_name'] ?? $row['name']).' · '.$row['specialty'].' · '.__('audited seller since').' '.$row['since'],
                'status' => 'approved',
                'approved_at' => $store->approved_at ?? now(),
                'state' => $this->stateFor($row['city']),
                'deleted_at' => null,
            ])->save();

            return [$row['brand'] => $store];
        });
    }

    /** @param  array<string, mixed>  $row */
    private function seedProduct(array $row, Store $store, Category $leaf): Product
    {
        // ⚠ The slug is NOT ours to choose: Product uses Spatie\Sluggable\HasSlug
        // and regenerates it from name.en on every save. Passing our own key made
        // firstOrNew match nothing and insert a duplicate on every run (98 of
        // them, twice). So key on exactly what the model will produce.
        $slug = Str::slug($row['name_en']);
        $priceSen = (int) round($row['price_myr'] * 100);

        $product = Product::withTrashed()->firstOrNew(['slug' => $slug]);
        $product->fill([
            'store_id' => $store->id,
            'category_id' => $leaf->id,
            'name' => ['en' => $row['name_en'], 'ms' => $row['name_ms']],
            'description' => $this->description($row),
            'status' => ProductStatus::Live,
            'published_at' => $product->published_at ?? now(),
            'cod_enabled' => true,
            // The certificate columns already exist on this table, so the badge
            // the revamped card renders is real data, not decoration.
            'halal_status' => HalalStatus::Certified,
            'halal_cert_number' => $this->certNumber($row['certifier'], $slug),
            'halal_cert_expiry' => now()->addMonths(6 + (crc32($slug) % 24))->startOfDay(),
            'rating_avg' => $row['rating'] ?? 0,
            'rating_count' => $row['reviews'] ?? 0,
            'sold_count' => ($row['reviews'] ?? 0) * 3,
            'deleted_at' => null,
        ])->save();

        // Single default variant per SKU: this is a grocery catalogue, not
        // apparel, so there is nothing to matrix.
        $variant = ProductVariant::firstOrNew([
            'product_id' => $product->id,
            'sku' => strtoupper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $slug), 0, 8)).'-'.substr((string) crc32($slug), 0, 4),
        ]);
        $variant->fill([
            'options_label' => $row['unit'],
            'price_sen' => $priceSen,
            'stock' => 40 + (crc32($slug) % 160),
            'is_default' => true,
            'position' => 0,
        ])->save();

        return $product->refresh();
    }

    /** @param  array<string, mixed>  $row */
    private function description(array $row): array
    {
        $unit = $row['unit'] ? ' '.$row['unit'].'.' : '';

        return [
            'en' => "{$row['name_en']}.{$unit} Certified by {$row['certifier']} and listed under {$row['leaf']}. The certificate is bound to this SKU, not to the shop.",
            'ms' => "{$row['name_ms']}.{$unit} Disahkan oleh {$row['certifier']} dan disenaraikan dalam {$row['leaf']}. Sijil terikat pada SKU ini, bukan pada kedai.",
        ];
    }

    private function certNumber(string $certifier, string $slug): string
    {
        $prefix = match ($certifier) {
            'JAKIM' => 'MY-JKM',
            'MUIS' => 'SG-MUIS',
            'BPJPH' => 'ID-BPJPH',
            'ESMA' => 'AE-ESMA',
            default => 'XX',
        };

        return $prefix.'-'.substr((string) crc32($slug), 0, 4).'-'.substr((string) crc32(strrev($slug)), 0, 3);
    }

    /** Attach the packshot if it exists on disk. Returns false when it does not,
     *  so the caller can report how many are still pending. */
    private function attachPackshot(Product $product, string $ref): bool
    {
        [$set, $file] = explode('/', $ref, 2);
        $root = self::IMAGE_ROOTS[$set] ?? null;

        if ($root === null) {
            return false;
        }

        $absolute = rtrim((string) getenv('HOME'), '/').'/'.$root.'/'.$file;

        if (! File::exists($absolute)) {
            return false;
        }

        // Re-attaching the same file every run would pile up media rows.
        if ($product->getMedia('images')->contains(fn ($m): bool => $m->file_name === basename($absolute))) {
            return true;
        }

        $product->clearMediaCollection('images');
        $product->addMedia($absolute)->preservingOriginal()->toMediaCollection('images');

        return true;
    }

    private function stateFor(string $city): string
    {
        return match ($city) {
            'Alor Setar' => 'Kedah',
            'Klang', 'Shah Alam', 'Bangi', 'Subang' => 'Selangor',
            'Ipoh', 'Teluk Intan' => 'Perak',
            'Prai' => 'Penang',
            'Kuantan' => 'Pahang',
            'Melaka' => 'Melaka',
            'Kuala Lumpur' => 'Kuala Lumpur',
            'Johor Bahru' => 'Johor',
            'Kuala Krai' => 'Kelantan',
            'Tanah Rata' => 'Pahang',
            'Putrajaya' => 'Putrajaya',
            'Cyberjaya', 'Nilai' => 'Negeri Sembilan',
            default => 'Kuala Lumpur',
        };
    }
}
