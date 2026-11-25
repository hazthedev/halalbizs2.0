<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // G247 (HB-DEC-1125b): OFF on live. A WHIP-confirmed certificate waits
        // for an admin; a superadmin can turn instant approval back on.
        $this->migrator->add('moderation.whip_auto_approve', false);
    }
};
