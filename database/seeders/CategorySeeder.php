<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * The marketplace's real department tree (2026-07-30 revamp): the five
     * departments the reference design ships, with bilingual leaves matching
     * the seeded halal-grocery catalogue. Two levels, department to leaf --
     * products attach to leaves.
     */
    private const TREE = [
        'Groceries & Pantry' => ['Barangan Dapur', [
            'Rice & Grains' => ['Beras & Bijirin', []],
            'Cooking Oils' => ['Minyak Masak', []],
            'Flour & Baking' => ['Tepung & Bakeri', []],
            'Sugar & Sweeteners' => ['Gula & Pemanis', []],
            'Spices & Seasoning' => ['Rempah & Perencah', []],
            'Sauces & Soy' => ['Sos & Kicap', []],
            'Canned & Preserved' => ['Makanan Tin', []],
            'Noodles & Pasta' => ['Mi & Pasta', []],
            'Dairy & Chilled' => ['Tenusu & Sejuk', []],
            'Honey & Spreads' => ['Madu & Sapuan', []],
        ]],
        'Food & Snacks' => ['Makanan & Snek', [
            'Biscuits & Cookies' => ['Biskut', []],
            'Chocolate & Sweets' => ['Coklat & Gula-gula', []],
            'Nuts & Dried Fruit' => ['Kacang & Buah Kering', []],
            'Dates' => ['Kurma', []],
            'Cereals & Bars' => ['Bijirin & Bar', []],
            'Crisps & Savoury Snacks' => ['Kerepek & Snek Masin', []],
        ]],
        'Drinks' => ['Minuman', [
            'Tea' => ['Teh', []],
            'Coffee' => ['Kopi', []],
            'Milk & Dairy Drinks' => ['Susu & Minuman Tenusu', []],
            'Juices & Water' => ['Jus & Air Mineral', []],
        ]],
        'Cosmetics & Care' => ['Kosmetik & Penjagaan', [
            'Skin Care' => ['Penjagaan Kulit', []],
            'Hair Care' => ['Penjagaan Rambut', []],
            'Body & Bath' => ['Badan & Mandian', []],
            'Fragrance' => ['Minyak Wangi', []],
            'Oral Care' => ['Penjagaan Mulut', []],
        ]],
        'Pharma & Supplements' => ['Farmasi & Suplemen', [
            'Vitamins & Minerals' => ['Vitamin & Mineral', []],
            'Herbal & Traditional' => ['Herba & Tradisional', []],
            'Sports Nutrition' => ['Nutrisi Sukan', []],
        ]],
    ];

    public function run(): void
    {
        $position = 0;
        $slugs = [];

        foreach (self::TREE as $en => [$ms, $children]) {
            $this->createNode($en, $ms, $children, null, $position++, $slugs);
        }

        // Retire anything outside the tree (the pre-revamp Electronics / Fashion
        // / Home & Living demo tree) by DEACTIVATING, not deleting: products
        // still reference those category ids until they are reseeded, and a
        // delete would either fail on the constraint or orphan them. Inactive
        // is enough to drop them from the storefront, and it is reversible.
        Category::whereNotIn('slug', $slugs)->update(['is_active' => false]);
    }

    private function createNode(string $en, string $ms, array $children, ?int $parentId, int $position, array &$slugs): void
    {
        $slug = Str::slug($en);
        $slugs[] = $slug;

        $category = Category::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => ['en' => $en, 'ms' => $ms],
                'parent_id' => $parentId,
                'position' => $position,
                'is_active' => true,
            ],
        );

        $childPosition = 0;

        foreach ($children as $childEn => [$childMs, $grandchildren]) {
            $this->createNode($childEn, $childMs, $grandchildren, $category->id, $childPosition++, $slugs);
        }
    }
}
