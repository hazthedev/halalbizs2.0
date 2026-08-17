<?php

namespace Database\Seeders;

use App\Models\HomeSection;
use Illuminate\Database\Seeder;

class HomeSectionSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            ['type' => 'banner', 'title' => null, 'payload' => null],
            ['type' => 'category_grid', 'title' => ['en' => 'Shop by category', 'ms' => 'Beli ikut kategori', 'vi' => 'Mua sắm theo danh mục'], 'payload' => ['limit' => 8]],
            ['type' => 'product_carousel', 'title' => ['en' => 'New on the market', 'ms' => 'Baru di pasaran', 'vi' => 'Sản phẩm mới trên sàn'], 'payload' => ['source' => 'latest', 'limit' => 12]],
            ['type' => 'recommended', 'title' => ['en' => 'Recommended for you', 'ms' => 'Disyorkan untuk anda', 'vi' => 'Gợi ý dành cho bạn'], 'payload' => null],
            ['type' => 'product_grid', 'title' => ['en' => 'Popular now', 'ms' => 'Popular sekarang', 'vi' => 'Đang được ưa chuộng'], 'payload' => ['source' => 'top', 'limit' => 12]],
            ['type' => 'recently_viewed', 'title' => ['en' => 'Recently viewed', 'ms' => 'Baru dilihat', 'vi' => 'Đã xem gần đây'], 'payload' => null],
        ];

        foreach ($sections as $i => $section) {
            HomeSection::updateOrCreate(
                ['type' => $section['type']],
                $section + ['position' => $i, 'is_active' => true],
            );
        }
    }
}
