<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('halal_certificates', fn (Blueprint $table) => $table->text('scope_note')->nullable()->change());
    }

    public function down(): void
    {
        // Keep the wider column: shrinking it could discard an imported WHIP scope.
    }
};
