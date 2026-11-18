<?php

use Illuminate\Pagination\LengthAwarePaginator;

it('renders the pager with theme tokens and an emerald current page', function () {
    $p = new LengthAwarePaginator(range(1, 20), 125, 20, 3, ['path' => '/x']);
    $html = view('livewire::tailwind', ['paginator' => $p, 'elements' => [[1 => '/x?page=1', 2 => '/x?page=2', 3 => '/x?page=3'], '...', [7 => '/x?page=7']]])->render();

    expect($html)->not->toMatch('/gray-|dark:|blue-|shadow-sm|rounded-md/')
        ->and($html)->toContain('bg-surface', 'border-line', 'text-ink-soft', 'min-h-11', 'wire:click="gotoPage(2')
        ->and($html)->toMatch('/<span aria-current="page">\s*<span class="[^"]*bg-emerald[^"]*text-white/');
});
