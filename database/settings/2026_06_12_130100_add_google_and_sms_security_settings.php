<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Google OAuth — dormant until both values are set (Turnstile pattern).
        $this->migrator->add('security.google_client_id', '');
        $this->migrator->addEncrypted('security.google_client_secret', '');

        // Future real SMS gateway — local stub logs instead of sending.
        $this->migrator->addEncrypted('security.sms_provider_key', '');
    }
};
