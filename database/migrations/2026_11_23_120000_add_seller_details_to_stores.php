<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HB-LAW-14 (HB-DEC-1123b): optional seller identity for the public shop
 * page. Nullable, no backfill: the shop page shows only what a seller fills.
 * The registration number reuses stores.registration_no.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('business_name', 120)->nullable();
            $table->string('business_address', 300)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stores', fn (Blueprint $t) => $t->dropColumn(['business_name', 'business_address', 'contact_phone', 'contact_email']));
    }
};
