<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', 'HalalBizs');
        $this->migrator->add('general.default_locale', 'en');
        $this->migrator->add('general.enabled_locales', ['en', 'ms']);
        $this->migrator->add('general.base_currency', 'MYR');
        $this->migrator->add('general.display_currencies', ['MYR', 'USD', 'SGD', 'IDR']);

        $this->migrator->add('commission.global_rate', 5.00);

        $this->migrator->add('order.return_window_days', 7);
        $this->migrator->add('order.auto_complete_days', 7);
        $this->migrator->add('order.unpaid_expiry_minutes', 60);
        $this->migrator->add('order.payout_min_sen', 5000);
        $this->migrator->add('order.return_seller_response_hours', 48);

        $this->migrator->add('cod.enabled', true);
        $this->migrator->add('cod.max_order_sen', 50000);

        $this->migrator->add('ipay88.merchant_code', '');
        $this->migrator->addEncrypted('ipay88.merchant_key', '');
        $this->migrator->add('ipay88.sandbox', true);

        $this->migrator->add('moderation.require_product_approval', false);

        $this->migrator->add('security.turnstile_site_key', '');
        $this->migrator->addEncrypted('security.turnstile_secret', '');

        $this->migrator->add('tracking.ga4_id', '');
        $this->migrator->add('tracking.meta_pixel_id', '');
        $this->migrator->add('tracking.tiktok_pixel_id', '');
    }
};
