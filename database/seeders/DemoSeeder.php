<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Local-only demo data: 10 approved stores, ~100 live products with
 * options/variants/images, a demo buyer, and homepage banners.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $leafCategories = Category::whereDoesntHave('children')->get();

        // Demo buyer with default address — used by Playwright journeys.
        $buyer = User::factory()->create([
            'name' => 'Demo Buyer',
            'email' => 'buyer@halalbizs.test',
        ]);
        $buyer->assignRole('buyer');
        Address::factory()->default()->create(['user_id' => $buyer->id]);

        // Demo seller account with a known login (first store below).
        $sellerUser = User::factory()->create([
            'name' => 'Demo Seller',
            'email' => 'seller@halalbizs.test',
        ]);

        foreach (range(1, 10) as $i) {
            $owner = $i === 1 ? $sellerUser : User::factory()->create();
            $owner->assignRole('seller');

            $store = Store::factory()->approved()->create(['user_id' => $owner->id]);
            $this->attachImage($store, 'logo', 240, 240, $store->name);
            $this->attachImage($store, 'banner', 1200, 300, $store->name);

            foreach (range(1, 10) as $j) {
                $factory = Product::factory()->for($store)->state([
                    'category_id' => $leafCategories->random()->id,
                ]);

                // Alternate: variant-matrix products and single-variant products.
                $product = ($j % 2 === 0)
                    ? $factory->withVariants(colour: 3, size: 2)->create()
                    : $factory->create();

                $this->attachImage($product, 'images', 600, 600, $product->getTranslation('name', 'en'));
            }
        }

        foreach (range(1, 3) as $i) {
            $banner = Banner::create([
                'title' => ['en' => "Campaign banner {$i}", 'ms' => "Sepanduk kempen {$i}"],
                'link_url' => '/search?q=new',
                'position' => $i,
                'is_active' => true,
            ]);
            $this->attachImage($banner, 'image', 1200, 400, "HalalBizs Campaign {$i}");
        }
    }

    /**
     * GD-generated flat placeholder PNG, attached via medialibrary.
     */
    private function attachImage($model, string $collection, int $w, int $h, string $label): void
    {
        $palette = [
            [4, 120, 87], [6, 95, 70], [25, 27, 26], [91, 97, 93],
            [180, 83, 9], [3, 57, 43], [71, 85, 105], [120, 53, 15],
        ];
        [$r, $g, $b] = $palette[abs(crc32($label)) % count($palette)];

        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));

        $text = strtoupper(substr($label, 0, 2));
        $white = imagecolorallocate($img, 247, 247, 244);
        $scale = (int) max(3, min(5, $w / 120));
        $x = (int) (($w - strlen($text) * imagefontwidth(5) * $scale) / 2);
        $y = (int) (($h - imagefontheight(5) * $scale) / 2);

        // Draw scaled-up text by rendering small and copying region-resized.
        $small = imagecreatetruecolor((int) ($w / $scale), (int) ($h / $scale));
        imagefill($small, 0, 0, imagecolorallocate($small, $r, $g, $b));
        $sx = (int) ((imagesx($small) - strlen($text) * imagefontwidth(5)) / 2);
        $sy = (int) ((imagesy($small) - imagefontheight(5)) / 2);
        imagestring($small, 5, $sx, $sy, $text, imagecolorallocate($small, 247, 247, 244));
        imagecopyresized($img, $small, 0, 0, 0, 0, $w, $h, imagesx($small), imagesy($small));
        imagedestroy($small);

        $path = tempnam(sys_get_temp_dir(), 'seed').'.png';
        imagepng($img, $path);
        imagedestroy($img);

        $model->addMedia($path)
            ->usingFileName(Str::slug($label).'.png')
            ->toMediaCollection($collection);
    }
}
