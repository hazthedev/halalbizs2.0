<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHIP is the source of truth for certificates it holds. `source` says who
 * vouches for a row; existing rows stay 'manual'. Stores gain the company
 * registration number that ties a WHIP certificate to its seller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('halal_certificates', function (Blueprint $table) {
            $table->string('source', 10)->default('manual')->index();
            $table->unsignedBigInteger('whip_application_id')->nullable();
            $table->timestamp('whip_synced_at')->nullable();
            $table->string('whip_answer', 20)->nullable();
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->string('registration_no', 40)->nullable();
            // Set when an admin approves a WHIP-matched certificate after checking
            // the SSM document. Until then the number is only the seller's claim.
            $table->timestamp('registration_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stores', fn (Blueprint $t) => $t->dropColumn(['registration_no', 'registration_verified_at']));
        Schema::table('halal_certificates', fn (Blueprint $t) => $t->dropColumn(['source', 'whip_application_id', 'whip_synced_at', 'whip_answer']));
    }
};
