<?php

use Illuminate\Support\Facades\Blade;

// 30 wide tables across admin and seller wrap themselves in
// <x-ui.card class="overflow-x-auto">. The scroll container holds no focusable
// cells, so without tabindex a keyboard user cannot reach the columns past the
// viewport edge — the mouse gets a visible scrollbar (deliberate: app.css hides
// scrollbars only under .shopfront) and the keyboard got nothing.
//
// Asserting on the rendered HTML rather than the class list: the whole point is
// what the browser receives.
test('a card that scrolls sideways is reachable by keyboard', function () {
    $html = Blade::render('<x-ui.card class="overflow-x-auto">rows</x-ui.card>');

    expect($html)->toContain('tabindex="0"')->toContain('role="group"');
});

test('an ordinary card is not a tab stop', function () {
    $html = Blade::render('<x-ui.card class="p-4">content</x-ui.card>');

    expect($html)->not->toContain('tabindex')->not->toContain('role="group"');
});

test('a label is announced when one is given', function () {
    $html = Blade::render('<x-ui.card class="overflow-x-auto" scroll-label="Payouts">rows</x-ui.card>');

    expect($html)->toContain('aria-label="Payouts"');
});
