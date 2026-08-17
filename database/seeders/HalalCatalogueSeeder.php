<?php

namespace Database\Seeders;

use App\Enums\HalalStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\Support\CatalogueProductName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
    /** Packshots live IN THE REPO, under seeders/data/packshots.
     *  They used to be read from a folder in $HOME, which worked locally and
     *  would have seeded the preview with 166 imageless products, because the
     *  deploy only ever has what is committed. 166 WebP files, 5.4 MB total
     *  (69 MB as PNG). */
    private const PACKSHOT_DIR = 'seeders/data/packshots';

    /** One random password shared by this run's demo sellers, printed once below.
     *  It used to be the literal string 'password' -- and because seedStores()
     *  fill()s an EXISTING user, every deploy reset all 19 accounts back to it.
     *  The store names are printed on the storefront and the email is derived
     *  from them, so that was nineteen published logins into an approved seller
     *  centre. Random-per-run keeps the demo signable-in (read the line below)
     *  and makes the next deploy rotate whatever leaked. */
    private ?string $demoPassword = null;

    private function demoPassword(): string
    {
        return $this->demoPassword ??= Str::password(20, symbols: false);
    }

    public function run(): void
    {
        $path = database_path('seeders/data/halalbizs-catalogue.json');

        if (! File::exists($path)) {
            $this->command?->error("Catalogue not found at {$path}");

            return;
        }

        $data = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        $vi = json_decode(File::get(database_path('seeders/data/vietnamese-catalogue.json')), true, 512, JSON_THROW_ON_ERROR);

        $leaves = Category::query()
            ->whereNotNull('parent_id')
            ->get()
            ->keyBy(fn (Category $c): string => $c->getTranslation('name', 'en'));

        $stores = $this->seedStores($data['stores']);

        $seeded = 0;
        $withImage = 0;
        $missingImages = [];

        foreach ($data['products'] as $row) {
            // The original catalogue accidentally copied Malay into name_en for
            // most generated SKUs. Keep that legacy value only as the stable
            // lookup identity, then persist genuinely English storefront copy.
            $row['legacy_name_en'] = $row['name_en'];
            $row['name_en'] = CatalogueProductName::for($row);

            $store = $stores[$row['brand']] ?? null;
            $leaf = $leaves[$row['leaf']] ?? null;

            if (! $store || ! $leaf) {
                $this->command?->warn("Skipped {$row['name_ms']}: unknown ".($store ? 'leaf '.$row['leaf'] : 'brand '.$row['brand']));

                continue;
            }

            $product = $this->seedProduct($row, $store, $leaf, $vi);
            $seeded++;

            if ($this->attachPackshot($product, $row['image'])) {
                $withImage++;
            } else {
                $missingImages[] = $row['image'];
            }
        }

        // Retire anything left in a category this tree deactivated — i.e. the
        // factory demo products (Latin names, no media on disk). DELISTED, not
        // deleted: orders, reviews and ledger rows reference them, so a delete
        // would either trip a constraint or orphan history.
        //
        // This was done by hand locally and therefore did NOT happen on the
        // preview, which then advertised 266 listings: 166 real ones plus 100
        // Latin-named demos with broken images. Reproducible now.
        $retired = Product::query()
            ->where('status', ProductStatus::Live)
            ->whereHas('category', fn ($q) => $q->where('is_active', false))
            ->update(['status' => ProductStatus::Delisted]);

        Cache::forget('hb.listing-count');
        Cache::forget('landing:stats');

        $this->command?->info("Stores: {$stores->count()} · products: {$seeded} · with packshot: {$withImage} · retired demo: {$retired}");
        $this->command?->warn('Demo seller password THIS RUN (rotates on every seed): '.$this->demoPassword());

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
                'password' => bcrypt($this->demoPassword()),
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
            // user_id/status are guarded — matching above is fine, but a NEW
            // row would be created without them, so both are set explicitly.
            $store->user_id = $user->id;
            $store->status = 'approved';
            $store->fill([
                'slug' => $slug,
                'name' => $row['name'],
                // Display name is the short brand (as the reference's cards and
                // storefront show it); the legal entity lives in the description.
                'description' => ($row['legal_name'] ?? $row['name']).' · '.$row['specialty'].' · '.__('audited seller since').' '.$row['since'],
                'approved_at' => $store->approved_at ?? now(),
                'state' => $this->stateFor($row['city']),
                'deleted_at' => null,
            ])->save();

            return [$row['brand'] => $store];
        });
    }

    /** @param  array<string, mixed>  $row */
    private function seedProduct(array $row, Store $store, Category $leaf, array $vi): Product
    {
        // ⚠ The slug is NOT ours to choose: Product uses Spatie\Sluggable\HasSlug
        // and regenerates it from name.en on every save. Passing our own key made
        // firstOrNew match nothing and insert a duplicate on every run (98 of
        // them, twice). So key on exactly what the model will produce.
        $slug = Str::slug($row['name_en']);
        $legacySlug = Str::slug($row['legacy_name_en']);
        $priceSen = (int) round($row['price_myr'] * 100);

        // Prefer the corrected canonical slug, but adopt the existing row by
        // its former Malay-derived slug on the first corrective deployment.
        // This keeps product ids, media, reviews and order history intact.
        $product = Product::withTrashed()->where('slug', $slug)->first()
            ?? Product::withTrashed()->where('slug', $legacySlug)->first()
            ?? new Product;
        $product->fill([
            'store_id' => $store->id,
            'category_id' => $leaf->id,
            'name' => ['en' => $row['name_en'], 'ms' => $row['name_ms'], 'vi' => $this->vietnameseName($row, $vi)],
            'description' => $this->description($row, $vi),
            'status' => ProductStatus::Live,
            'published_at' => $product->published_at ?? now(),
            'cod_enabled' => true,
            // The certificate columns already exist on this table, so the badge
            // the revamped card renders is real data, not decoration.
            'halal_status' => HalalStatus::Certified,
            // Certificate demo identities predate this language correction.
            // Derive them from the legacy slug so the register does not mint a
            // second certificate merely because its product title was fixed.
            'halal_cert_number' => $this->certNumber($row['certifier'], $legacySlug),
            'halal_cert_expiry' => now()->addMonths(6 + (crc32($slug) % 24))->startOfDay(),
            'rating_avg' => $row['rating'] ?? 0,
            'rating_count' => $row['reviews'] ?? 0,
            'sold_count' => ($row['reviews'] ?? 0) * 3,
            'deleted_at' => null,
        ])->save();

        // Single default variant per SKU: this is a grocery catalogue, not
        // apparel, so there is nothing to matrix.
        // A title correction must not create a second default variant or change
        // the SKU already referenced by carts and order lines.
        $variant = ProductVariant::where('product_id', $product->id)->where('is_default', true)->first()
            ?? new ProductVariant([
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
    private function description(array $row, array $vi): array
    {
        $unit = $row['unit'] ? ' '.$row['unit'].'.' : '';

        return [
            'en' => "{$row['name_en']}.{$unit} Certified by {$row['certifier']} and listed under {$row['leaf']}. The certificate is bound to this SKU, not to the shop.",
            'ms' => "{$row['name_ms']}.{$unit} Disahkan oleh {$row['certifier']} dan disenaraikan dalam {$row['leaf']}. Sijil terikat pada SKU ini, bukan pada kedai.",
            'vi' => "{$this->vietnameseName($row, $vi)}.{$unit} Được {$row['certifier']} chứng nhận và xếp trong danh mục {$vi['categories'][$row['leaf']]}. Chứng nhận được liên kết với SKU này, không phải với cửa hàng.",
        ];
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $vi */
    private function vietnameseName(array $row, array $vi): string
    {
        return $vi['products'][$row['legacy_name_en']]
            ?? $vi['products'][$row['name_ms']]
            ?? $vi['products'][$row['name_en']];
    }

    private function certNumber(string $certifier, string $slug): string
    {
        // Third copy of the body table, retired: HalalCertificate::BODIES owns it.
        $prefix = HalalCertificate::BODIES[$certifier]['prefix'] ?? 'XX';

        return $prefix.'-'.substr((string) crc32($slug), 0, 4).'-'.substr((string) crc32(strrev($slug)), 0, 3);
    }

    /** Attach the packshot if it exists on disk. Returns false when it does not,
     *  so the caller can report how many are still pending. */
    private function attachPackshot(Product $product, string $ref): bool
    {
        $absolute = database_path(self::PACKSHOT_DIR.'/'.basename($ref));

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
