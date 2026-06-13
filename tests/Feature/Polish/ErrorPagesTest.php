<?php

test('a missing route renders the branded 404 page with search and a home action', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('HalalBizs')
        ->assertSee('404')
        ->assertSee('Page not found.')
        ->assertSee('action="'.url('/search').'"', false)
        ->assertSee('Browse home');
});

test('the other branded error views render with the wordmark and a single sentence', function (string $view, string $code, string $needle) {
    $html = view("errors.{$view}")->render();

    expect($html)
        ->toContain('HalalBizs')
        ->toContain($code)
        ->toContain($needle);
})->with([
    ['403', '403', 'Back to home'],
    ['500', '500', 'Back to home'],
    ['503', '503', 'Back shortly.'],
]);
