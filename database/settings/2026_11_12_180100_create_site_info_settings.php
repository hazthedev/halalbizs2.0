<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach (['contact_email', 'contact_phone', 'contact_whatsapp', 'contact_address', 'social_facebook', 'social_instagram', 'social_tiktok', 'social_youtube', 'social_x'] as $key) {
            $this->migrator->add("site_info.$key", '');
        }
    }
};
