<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Search engine / share text, per language like title and body. Empty = fall back to the title and the page text.
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            // A custom page ticked here gets a link in the website footer.
            $table->boolean('show_in_footer')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'seo_description', 'show_in_footer']);
        });
    }
};
