<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('two_factor_method')->nullable()->after('password'); // email|totp (App\Enums\TwoFactorMethod)
            $table->text('two_factor_secret')->nullable()->after('two_factor_method');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->string('google_id')->nullable()->index()->after('remember_token');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
        });

        // Short-lived one-time codes (email 2FA + phone verification).
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose'); // 2fa-email|phone-verify
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['google_id']);
            $table->dropColumn([
                'two_factor_method',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'google_id',
                'phone_verified_at',
            ]);
        });
    }
};
