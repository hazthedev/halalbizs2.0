<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Str;

/** Resolves the English storefront name for every committed demo SKU. */
final class CatalogueProductName
{
    /** @var array<string, string> */
    private const REFERENCE_OVERRIDES = [
        'beras-basmathi-aged-12-months' => 'Aged Basmati Rice, 12 Months',
        'minyak-masak-sawi-tulen' => 'Pure Mustard Cooking Oil',
        'tepung-gandum-serbaguna' => 'All-Purpose Wheat Flour',
        'gula-pasir-halus' => 'Fine Granulated Sugar',
        'mi-segera-perisa-ayam' => 'Instant Noodles, Chicken Flavour',
        'tuna-dalam-minyak' => 'Tuna in Oil',
        'sos-cili-pedas-manis' => 'Sweet & Spicy Chilli Sauce',
        'kurma-premium-ajwa' => 'Premium Ajwa Dates',
        'susu-tepung-penuh-krim' => 'Full Cream Milk Powder',
        'susu-coklat-uht' => 'UHT Chocolate Milk',
        'biskut-marie-klasik' => 'Classic Marie Biscuits',
        'madu-asli-hutan' => 'Raw Forest Honey',
        'teh-hijau-premium' => 'Premium Green Tea',
        'serbuk-kari-daging' => 'Beef Curry Powder',
        'santan-kelapa-pekat' => 'Thick Coconut Milk',
        'lada-hitam-tumbuk-sarawak' => 'Ground Sarawak Black Pepper',
        'kiub-pati-ayam' => 'Chicken Stock Cubes',
        'coklat-dark-70-koko' => '70% Dark Chocolate',
        'mentega-kacang-krim' => 'Creamy Peanut Butter',
        'oat-segera-bijirin-penuh' => 'Instant Wholegrain Oats',
        'syampu-herba-nourish-care' => 'Nourish & Care Herbal Shampoo',
        'multivitamin-harian-60-tablet' => 'Daily Multivitamin, 60 Tablets',
        'chickpeas-kacang-kuda' => 'Chickpeas',
    ];

    /** @var array<string, string> */
    private const GENERATED_OVERRIDES = [
        'instant-noodles-tomyam' => 'Instant Tom Yum Noodles',
        'instant-teh-tarik-3-in-1' => 'Instant Teh Tarik 3-in-1',
        '85-dark-chocolate' => '85% Dark Chocolate',
        'spf50-sunscreen' => 'SPF 50 Sunscreen',
        'vitamin-c-1000mg' => 'Vitamin C 1000 mg',
        'omega-3-fish-oil' => 'Omega-3 Fish Oil',
        'ready-to-eat-butter-popcorn' => 'Ready-to-Eat Butter Popcorn',
        'no-added-sugar-orange-juice' => 'No-Added-Sugar Orange Juice',
        'instant-coffee-3-in-1' => 'Instant Coffee 3-in-1',
        'manuka-honey-umf10' => 'Manuka Honey UMF 10+',
    ];

    /** @param array<string, mixed> $row */
    public static function for(array $row): string
    {
        $key = (string) $row['key'];

        if (($row['source'] ?? null) === 'reference') {
            return self::REFERENCE_OVERRIDES[$key] ?? (string) $row['name_en'];
        }

        return self::GENERATED_OVERRIDES[$key]
            ?? Str::of($key)->replace('-', ' ')->title()->toString();
    }
}
