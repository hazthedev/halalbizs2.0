<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M0.2 — e-invoicing. A document per supplier (store): an individual e-invoice
 * per sub-order, or one consolidated B2C document per store per period. Built
 * from the SACRED order snapshots and submitted to a pluggable provider
 * (LHDN MyInvois first). TIN columns carry the tax identifiers needed on the
 * document. All money is integer sen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('einvoice_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete()->index();
            // Individual docs reference a sub-order + its order; consolidated docs leave them null.
            $table->foreignId('sub_order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');                 // myinvois | null
            $table->string('type');                     // App\Enums\EInvoiceType
            $table->string('period')->nullable();       // YYYY-MM for consolidated
            $table->string('status')->default('pending')->index(); // App\Enums\EInvoiceStatus
            $table->unsignedBigInteger('total_sen')->default(0);
            $table->unsignedBigInteger('tax_sen')->default(0);
            $table->string('submission_uid')->nullable();
            $table->string('uin')->nullable();          // long id from the tax authority
            $table->string('validation_url')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'period', 'type']);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->string('tin')->nullable();          // seller tax identification no.
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('tin')->nullable();          // buyer TIN for individual e-invoices
        });

        Schema::table('orders', function (Blueprint $table) {
            // Buyer asked for a full (individual) e-invoice rather than B2C consolidation.
            $table->boolean('einvoice_requested')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('einvoice_documents');
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('tin'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('tin'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('einvoice_requested'));
    }
};
