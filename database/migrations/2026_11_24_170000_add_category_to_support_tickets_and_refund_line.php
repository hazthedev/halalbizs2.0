<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** HB-B5D: ticket category (report a listing, privacy request) + the LAW-17 line on the live refund page. */
return new class extends Migration
{
    private const LINES = [
        'en' => '<h3>6. Return delivery cost</h3><p>If the item is faulty or the wrong item was sent, the seller pays the re-delivery. If you change your mind, you pay.</p>',
        'ms' => '<h3>6. Kos penghantaran semula</h3><p>Jika barang rosak atau salah dihantar, penjual menanggung kos penghantaran semula. Jika anda berubah fikiran, anda menanggungnya.</p>',
        'vi' => '<h3>6. Chi phí giao lại</h3><p>Nếu hàng bị lỗi hoặc giao sai, người bán chịu phí giao lại. Nếu bạn đổi ý, bạn chịu phí.</p>',
    ];

    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('category')->default('general')->index()->after('priority'); // general|report_listing|privacy
        });

        // The page is CMS content (the seeder never overwrites it): add the line to the live row, once.
        $page = Page::where('slug', 'refund-policy')->first();

        if ($page === null) {
            return;
        }

        $body = $page->getTranslations('body');

        foreach (self::LINES as $locale => $line) {
            $text = $body[$locale] ?? '';

            if ($text === '' || str_contains($text, $line)) {
                continue;
            }

            $body[$locale] = str_contains($text, '<p><em>') ? preg_replace('/<p><em>/', $line.'<p><em>', $text, 1) : $text.$line;
        }

        $page->setTranslations('body', $body)->saveQuietly();
    }

    public function down(): void
    {
        Schema::table('support_tickets', fn (Blueprint $table) => $table->dropColumn('category'));
    }
};
