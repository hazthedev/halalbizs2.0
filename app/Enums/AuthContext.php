<?php

namespace App\Enums;

/**
 * Which door a user came through to reach the shared auth screens.
 *
 * There is ONE login/register component per purpose; the context only reframes
 * copy and cross-links and picks a sensible landing when nothing else was
 * intended. It grants no privilege — a buyer who opens /seller/login is still
 * just a buyer, routed by PostLoginRedirect exactly as before.
 */
enum AuthContext: string
{
    case Storefront = 'storefront';
    case Seller = 'seller';
    case Admin = 'admin';

    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Storefront;
    }

    public function loginTitle(): string
    {
        return match ($this) {
            self::Storefront => __('Log in'),
            self::Seller => __('Seller log in'),
            self::Admin => __('Admin log in'),
        };
    }

    public function loginSubtitle(): string
    {
        return match ($this) {
            self::Storefront => __('Welcome back — your cart is right where you left it.'),
            self::Seller => __('Sign in to your Seller Centre.'),
            self::Admin => __('Authorised staff only. All access is logged.'),
        };
    }

    /** The landing when no legitimate intended URL survived login. null = storefront home. */
    public function homeRoute(): ?string
    {
        return match ($this) {
            self::Storefront => null,
            self::Seller => 'seller.dashboard',
            self::Admin => 'admin.dashboard',
        };
    }
}
