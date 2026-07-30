<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The halal certificate layer.
 *
 * Modelled as its own record rather than more columns on products, because that
 * is how certification actually works and how the reference design presents it:
 * an issuing body certifies a HOLDER (the seller's legal entity) for a SCOPE,
 * and that one certificate covers many SKUs. The register screen shows the
 * record plus "the 42 covered SKUs"; the product page shows the certificate the
 * SKU is bound to.
 *
 * products.halal_cert_number stays as it is — it is the number as printed on the
 * listing, and keeping it means nothing that already reads it breaks. The
 * foreign key below is what carries the verifiable record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('halal_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // The number as printed. Unique, because two records claiming one
            // number is exactly the ambiguity a register exists to remove.
            $table->string('number')->unique();

            $table->string('issuing_body');              // JAKIM / MUIS / BPJPH / ESMA
            $table->string('issuing_body_name')->nullable();
            $table->string('holder_name');               // the certified legal entity
            $table->string('scheme')->nullable();        // e.g. MS 1500:2019
            $table->string('scope_note')->nullable();    // "dry goods, spice blends, sauces"

            $table->date('valid_from');
            $table->date('valid_to');

            $table->string('facility')->nullable();

            // The two assurances the reference lets buyers filter on. Booleans,
            // because a buyer filters on them — nullable prose could not answer
            // "show me only dedicated-facility products".
            $table->boolean('dedicated_facility')->default(false);
            $table->boolean('export_paperwork')->default(false);

            $table->timestamps();

            $table->index(['issuing_body', 'valid_to']);
        });

        // The audit trail. Append-only in practice: these are things that
        // happened, so they get recorded rather than edited.
        Schema::create('halal_certificate_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('halal_certificate_id')->constrained()->cascadeOnDelete();
            $table->date('occurred_on');
            $table->string('summary');
            $table->timestamps();

            $table->index(['halal_certificate_id', 'occurred_on']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('halal_certificate_id')->nullable()->after('halal_cert_expiry')
                ->constrained()->nullOnDelete();

            // Per-batch traceability belongs to the PRODUCT, not the
            // certificate: one certificate covers many SKUs, but a batch code
            // and a packing date belong to the individual item.
            $table->string('halal_batch_code')->nullable()->after('halal_certificate_id');
            $table->date('halal_packed_on')->nullable()->after('halal_batch_code');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['halal_certificate_id']);
            $table->dropColumn(['halal_certificate_id', 'halal_batch_code', 'halal_packed_on']);
        });

        Schema::dropIfExists('halal_certificate_events');
        Schema::dropIfExists('halal_certificates');
    }
};
