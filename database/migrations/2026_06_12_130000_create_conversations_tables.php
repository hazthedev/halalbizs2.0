<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One thread per buyer↔store pair. Sellers chat AS the store, never
        // as a personal account — buyer_id is always the customer side.
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['buyer_id', 'store_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('sender_type'); // buyer | seller (string-backed, no DB enum)
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            // Optional product context chip ("asking about this item") —
            // nullOnDelete keeps history readable after a product is removed.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable(); // messages are immutable: no updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
