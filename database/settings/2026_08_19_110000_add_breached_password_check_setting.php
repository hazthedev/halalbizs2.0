<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // TRUE keeps today's behaviour: Password::defaults() has enforced
        // uncompromised() since 2026-08-10, added after the live superadmin was
        // found using `12345678` under a rule that was only min(8).
        //
        // The toggle exists so an admin can turn the breach check off without a
        // deploy. It defaults ON for the same reason purchasing_enabled defaults
        // ON — a new setting must never silently change what the app already
        // does. Minimum length is deliberately NOT part of it.
        $this->migrator->add('security.breached_password_check', true);
    }
};
