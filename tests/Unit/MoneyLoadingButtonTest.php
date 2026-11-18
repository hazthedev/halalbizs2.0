<?php

use Illuminate\Support\Facades\Blade;

uses(Tests\TestCase::class);

it('renders a scoped double-click guard on each money form', function (string $view, string $action) {
    $source = file_get_contents(resource_path('views/livewire/'.$view.'.blade.php'));
    preg_match('/<form\b[^>]*wire:submit="'.preg_quote($action, '/').'"[\s\S]*?<\/form>/', $source, $form);
    preg_match('/<(button|x-ui.button)\b(?=[^>]*type="submit")(?:[^>"\']|"[^"]*"|\'[^\']*\')*>[\s\S]*?<\/\1>/', $form[0], $button);
    $html = Blade::render($button[0]);
    expect($html)->toContain('wire:loading.attr="disabled"', 'wire:target="'.$action.'"', 'motion-safe:animate-spin');
})->with([
    ['storefront/account/affiliate', 'requestPayout'],
    ['admin/affiliates/index', 'markPaid'],
    ['admin/affiliates/index', 'reject'],
    ['admin/coins/index', 'adjust'],
    ['admin/content/flash-sales', 'createSale'],
    ['admin/content/flash-sales', 'addItem'],
    ['seller/live-sessions/form', 'save'],
]);
