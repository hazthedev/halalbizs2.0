<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_versions', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id');
            // draft = waiting for a publisher (one per item); published = history.
            $table->string('status', 12);
            $table->json('payload');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'status'], 'content_versions_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_versions');
    }
};
