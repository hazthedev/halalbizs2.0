<?php

test('M2 + chrome UI strings resolve from lang/ms.json', function () {
    app()->setLocale('ms');

    expect(__('Add to cart'))->toBe('Tambah ke troli')
        ->and(__('Coin balance'))->toBe('Baki syiling')
        ->and(__('Creator program'))->toBe('Program pencipta')
        ->and(__('My subscriptions'))->toBe('Langganan saya')
        ->and(__('Ask the concierge'))->toBe('Tanya konsierj');
});

test('placeholders survive translation', function () {
    app()->setLocale('ms');

    expect(__('Subscribe & save :pct%', ['pct' => 5]))->toBe('Langgan & jimat 5%')
        ->and(__('with :n people', ['n' => 3]))->toBe('dengan 3 orang');
});

test('untranslated keys fall back to the English source', function () {
    app()->setLocale('ms');

    // A string not in ms.json renders its English key (graceful fallback).
    expect(__('this string is intentionally not translated'))->toBe('this string is intentionally not translated');
});
