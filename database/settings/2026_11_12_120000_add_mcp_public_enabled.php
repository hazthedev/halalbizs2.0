<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.mcp_public_enabled', false);
    }

    public function down(): void
    {
        $this->migrator->delete('general.mcp_public_enabled');
    }
};
