<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // RM100: a store owing more than this in commission cannot take COD.
        $this->migrator->add('cod.debt_limit_sen', 10000);
    }
};
