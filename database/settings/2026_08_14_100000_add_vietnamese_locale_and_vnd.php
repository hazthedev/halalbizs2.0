<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Vietnamese as a fourth UI locale and VND as a fifth display currency.
 *
 * Both are appends, not replacements — the storefront switchers read these two
 * arrays directly, so this is what actually turns them on. The VND *row* comes
 * from CurrencySeeder, which deploy.sh re-runs every deploy; until it lands,
 * CurrencyConverter finds no row and falls back to MYR rather than erroring.
 *
 * UI chrome only. Seller/admin CONTENT (product names, categories, banners,
 * CMS pages) is still an en/ms pair — see the `vi` follow-up note in the PR.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update(
            'general.enabled_locales',
            fn (array $locales) => array_values(array_unique([...$locales, 'vi'])),
        );

        $this->migrator->update(
            'general.display_currencies',
            fn (array $codes) => array_values(array_unique([...$codes, 'VND'])),
        );
    }
};
