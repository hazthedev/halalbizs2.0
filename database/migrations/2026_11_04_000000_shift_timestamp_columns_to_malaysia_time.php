<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->shift('+');
    }

    public function down(): void
    {
        $this->shift('-');
    }

    private function shift(string $direction): void
    {
        foreach (DB::select("select table_name t, column_name c from information_schema.columns
            where table_schema = database() and data_type = 'timestamp'
            and table_name not in ('failed_jobs','migrations')") as $column) {
            $table = str_replace('`', '``', $column->t);
            $name = str_replace('`', '``', $column->c);

            DB::statement("update `{$table}` set `{$name}` = `{$name}` {$direction} interval 8 hour where `{$name}` is not null");
        }
    }
};
