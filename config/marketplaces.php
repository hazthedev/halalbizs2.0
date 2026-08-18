<?php

/**
 * External marketplaces a seller may link a product to.
 *
 * This file is the ALLOW-LIST. A URL whose host is not named here cannot be
 * saved at all — a halal marketplace vouches for where it sends people, so an
 * unrecognised host is refused rather than rendered as a generic button.
 * Adding a platform is a row here and nothing else.
 *
 * `hosts` are matched exactly or on a dot boundary by MarketplaceLinkResolver
 * (`shopee.com.my` matches `my.shopee.com.my` but never `notshopee.com.my`).
 * List the registrable domain, not a subdomain, unless the platform really does
 * live on one (TikTok Shop does).
 *
 * `label` is a brand name and is deliberately NOT translated.
 */
return [

    'platforms' => [

        'shopee' => [
            'label' => 'Shopee',
            'hosts' => ['shopee.com.my', 'shopee.sg', 'shopee.co.id', 'shopee.co.th', 'shopee.ph', 'shopee.vn'],
        ],

        'lazada' => [
            'label' => 'Lazada',
            'hosts' => ['lazada.com.my', 'lazada.sg', 'lazada.co.id', 'lazada.co.th', 'lazada.com.ph', 'lazada.vn'],
        ],

        'tiktok' => [
            'label' => 'TikTok Shop',
            'hosts' => ['shop.tiktok.com'],
        ],

        'zalora' => [
            'label' => 'Zalora',
            'hosts' => ['zalora.com.my', 'zalora.sg'],
        ],

    ],

];
