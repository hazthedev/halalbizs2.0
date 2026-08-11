<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The PDP reads product_questions through the visible() scope
     * (product_id + is_hidden); until now only the FK index existed, so the
     * hot storefront read filtered is_hidden by scan.
     */
    public function up(): void
    {
        Schema::table('product_questions', function (Blueprint $table) {
            $table->index(['product_id', 'is_hidden']);
        });
    }

    public function down(): void
    {
        Schema::table('product_questions', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'is_hidden']);
        });
    }
};
