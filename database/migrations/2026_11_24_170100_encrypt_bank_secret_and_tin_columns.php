<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HB-G198 (HB-DEC-1124f item 6): seller bank details (and the payout copies of
 * them), webhook signing secrets and users.tin are encrypted with APP_KEY.
 *
 * STORED-VALUE CHANGE: existing rows are encrypted in place. Losing APP_KEY
 * loses these values for good, so back APP_KEY up off the server first.
 *
 * Columns widen to TEXT (ciphertext is ~4x the plain length and is not JSON).
 * The row pass is idempotent: a value that already decrypts is left alone,
 * so a re-run after a partial failure finishes the job. down() decrypts back.
 */
return new class extends Migration
{
    /** table => [column, nullable, original type] */
    private const COLUMNS = [
        'stores' => ['bank_details', true, 'json'],
        'payouts' => ['bank_snapshot', false, 'json'],
        'affiliate_payouts' => ['bank_snapshot', true, 'json'],
        'webhook_subscriptions' => ['secret', false, 'string'],
        'users' => ['tin', true, 'string'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => [$column, $nullable]) {
            Schema::table($table, fn (Blueprint $t) => $t->text($column)->nullable($nullable)->change());

            $this->eachValue($table, $column, fn (string $value) => $this->decrypts($value) ? null : Crypt::encryptString($value));
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => [$column, $nullable, $type]) {
            $this->eachValue($table, $column, fn (string $value) => $this->decrypts($value) ? Crypt::decryptString($value) : null);

            Schema::table($table, fn (Blueprint $t) => $t->{$type}($column)->nullable($nullable)->change());
        }
    }

    /** Rewrite each non-null value $map returns a new string for (null = leave it). */
    private function eachValue(string $table, string $column, Closure $map): void
    {
        DB::table($table)->whereNotNull($column)->select('id', $column)->lazyById(500)
            ->each(function (object $row) use ($table, $column, $map) {
                $new = $map((string) $row->{$column});

                if ($new !== null) {
                    DB::table($table)->where('id', $row->id)->update([$column => $new]);
                }
            });
    }

    private function decrypts(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
