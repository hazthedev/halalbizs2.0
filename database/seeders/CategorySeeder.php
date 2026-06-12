<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * 3-level sample tree. Keys: en name => [ms name, children].
     */
    private const TREE = [
        'Electronics' => ['Elektronik', [
            'Mobile & Gadgets' => ['Telefon & Gajet', [
                'Smartphones' => ['Telefon Pintar', []],
                'Tablets' => ['Tablet', []],
                'Wearables' => ['Peranti Boleh Pakai', []],
            ]],
            'Computers' => ['Komputer', [
                'Laptops' => ['Komputer Riba', []],
                'Accessories' => ['Aksesori Komputer', []],
            ]],
            'Audio' => ['Audio', [
                'Earphones' => ['Fon Telinga', []],
                'Speakers' => ['Pembesar Suara', []],
            ]],
        ]],
        'Fashion' => ['Fesyen', [
            "Men's Wear" => ['Pakaian Lelaki', [
                'Shirts' => ['Kemeja', []],
                'Pants' => ['Seluar', []],
            ]],
            "Women's Wear" => ['Pakaian Wanita', [
                'Dresses' => ['Pakaian Dress', []],
                'Hijab & Scarves' => ['Tudung & Selendang', []],
            ]],
            'Shoes & Bags' => ['Kasut & Beg', [
                'Sneakers' => ['Kasut Sukan', []],
                'Handbags' => ['Beg Tangan', []],
            ]],
        ]],
        'Home & Living' => ['Rumah & Kehidupan', [
            'Kitchen' => ['Dapur', [
                'Cookware' => ['Peralatan Memasak', []],
                'Storage' => ['Penyimpanan', []],
            ]],
            'Furniture' => ['Perabot', [
                'Living Room' => ['Ruang Tamu', []],
                'Bedroom' => ['Bilik Tidur', []],
            ]],
            'Groceries' => ['Barangan Runcit', [
                'Snacks' => ['Snek', []],
                'Beverages' => ['Minuman', []],
            ]],
        ]],
    ];

    public function run(): void
    {
        $position = 0;

        foreach (self::TREE as $en => [$ms, $children]) {
            $this->createNode($en, $ms, $children, null, $position++);
        }
    }

    private function createNode(string $en, string $ms, array $children, ?int $parentId, int $position): void
    {
        $category = Category::updateOrCreate(
            ['slug' => Str::slug($en)],
            [
                'name' => ['en' => $en, 'ms' => $ms],
                'parent_id' => $parentId,
                'position' => $position,
                'is_active' => true,
            ],
        );

        $childPosition = 0;

        foreach ($children as $childEn => [$childMs, $grandchildren]) {
            $this->createNode($childEn, $childMs, $grandchildren, $category->id, $childPosition++);
        }
    }
}
