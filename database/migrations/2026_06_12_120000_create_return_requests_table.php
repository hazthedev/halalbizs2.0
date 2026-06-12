<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('return_reason_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            // App\Enums\ReturnStatus — string column, no DB enums (CLAUDE.md).
            $table->string('status')->default('requested');
            // Seller's dispute reason — shown in the admin escalation queue.
            $table->text('seller_response')->nullable();
            // Seller deadline: now + OrderSettings.return_seller_response_hours at creation.
            $table->timestamp('respond_by');
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
