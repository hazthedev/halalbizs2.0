<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Existing marketplaces keep today's commerce behaviour. Admin can
        // turn this off to make the storefront a browse-and-enquire directory.
        $this->migrator->add('general.purchasing_enabled', true);
    }
};
