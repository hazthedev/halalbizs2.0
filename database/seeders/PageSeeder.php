<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'about' => ['About Us', 'Tentang Kami'],
            'terms' => ['Terms & Conditions', 'Terma & Syarat'],
            'privacy' => ['Privacy Policy', 'Dasar Privasi'],
            'refund-policy' => ['Refund Policy', 'Dasar Bayaran Balik'],
            'faq' => ['FAQ', 'Soalan Lazim'],
        ];

        foreach ($pages as $slug => [$en, $ms]) {
            Page::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => ['en' => $en, 'ms' => $ms],
                    'body' => [
                        'en' => "<h2>{$en}</h2><p>Content for {$en} will be published before launch. Prices listed by sellers are final and tax-inclusive; the platform is not the seller of record.</p>",
                        'ms' => "<h2>{$ms}</h2><p>Kandungan untuk {$ms} akan diterbitkan sebelum pelancaran.</p>",
                    ],
                    'is_active' => true,
                ],
            );
        }
    }
}
