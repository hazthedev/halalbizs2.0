<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit H-6: the halal layer had no write path at all — halal_certificates was
 * populated by two demo seeders and nothing else. Giving sellers a way to
 * submit one, and admins a way to approve it, needs a review lifecycle the
 * table has never had.
 */
return new class extends Migration
{
    public function up(): void
    {
        // TWO STEPS, and the order is the point.
        //
        // Step 1 adds the column defaulting to 'approved', so every row that
        // already exists is backfilled as approved by the ALTER itself. Those
        // rows ARE the live catalogue — 24 certificates badging the storefront
        // — and defaulting them to 'pending' would un-verify the whole shop the
        // instant this migration ran.
        //
        // Step 2 then flips the DEFAULT to 'pending' for everything inserted
        // afterwards. Leaving it at 'approved' would mean any future writer
        // that forgets to set a status silently mints a trusted certificate —
        // fail-open on the one column that carries the trust claim. Existing
        // rows keep the value step 1 gave them; only the default changes.
        Schema::table('halal_certificates', function (Blueprint $table) {
            $table->string('status')->default('approved')->after('store_id')->index();

            // Who submitted it and when. submitted_at also drives the review
            // grace period, so a renewal under review does not get its products
            // delisted by our own turnaround time.
            $table->timestamp('submitted_at')->nullable()->after('export_paperwork');

            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                ->constrained('users')->nullOnDelete();

            // Shown to the seller on a rejection, so it must be their words back
            // to them, not an internal note.
            $table->text('review_note')->nullable()->after('reviewed_by');
        });

        Schema::table('halal_certificates', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('halal_certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['status', 'submitted_at', 'reviewed_at', 'review_note']);
        });
    }
};
