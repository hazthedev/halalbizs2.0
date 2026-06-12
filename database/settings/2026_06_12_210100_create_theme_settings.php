<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('theme.occasion', '');
        $this->migrator->add('theme.announcement_enabled', false);
        $this->migrator->add('theme.announcement_text_en', '');
        $this->migrator->add('theme.announcement_text_ms', '');
        $this->migrator->add('theme.announcement_bg', '#03392B');
        $this->migrator->add('theme.announcement_text_color', '#F7F7F4');
        $this->migrator->add('theme.hero_image_enabled', false);
        $this->migrator->add('theme.starts_at', null);
        $this->migrator->add('theme.ends_at', null);
    }
};
