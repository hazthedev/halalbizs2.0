<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', fn (Blueprint $table) => $table->unsignedBigInteger('whip_application_id')->nullable());
    }

    public function down(): void
    {
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('whip_application_id'));
    }
};
