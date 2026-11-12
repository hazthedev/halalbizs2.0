<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Shop contact details and social links: web footer and GET /api/v1/config. Empty string = not set. */
class SiteInfoSettings extends Settings
{
    public string $contact_email;

    public string $contact_phone;

    public string $contact_whatsapp;

    public string $contact_address;

    public string $social_facebook;

    public string $social_instagram;

    public string $social_tiktok;

    public string $social_youtube;

    public string $social_x;

    public static function group(): string
    {
        return 'site_info';
    }
}
