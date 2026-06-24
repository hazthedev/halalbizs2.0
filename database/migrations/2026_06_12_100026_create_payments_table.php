<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway');
            $table->string('ref_no')->index();
            $table->unsignedBigInteger('amount_sen');
            $table->char('currency', 3)->default('MYR');
            $table->string('status')->default('pending')->index();
            $table->string('ipay88_payment_id')->nullable();
            $table->string('ipay88_trans_id')->nullable();
            $table->string('ipay88_auth_code')->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->string('requery_result')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'ipay88_trans_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
