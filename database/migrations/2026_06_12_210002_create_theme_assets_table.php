<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tiny media anchor — settings can't hold uploads, so the seasonal
        // hero image hangs off a singleton row (key = 'hero').
        Schema::create('theme_assets', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_assets');
    }
};
