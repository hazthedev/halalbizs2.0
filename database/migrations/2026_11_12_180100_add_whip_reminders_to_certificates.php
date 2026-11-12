<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('halal_certificates', fn (Blueprint $table) => $table->json('whip_reminders')->nullable());
    }

    public function down(): void
    {
        Schema::table('halal_certificates', fn (Blueprint $table) => $table->dropColumn('whip_reminders'));
    }
};
