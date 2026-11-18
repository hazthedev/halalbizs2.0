<?php

use Illuminate\Support\Facades\Blade;

uses(Tests\TestCase::class);

it('spins and disables only for its own click action', function () {
    $html = Blade::render('<x-ui.button wire:click="save(42)">Save</x-ui.button>');

    expect($html)->toContain('wire:loading.attr="disabled"', 'wire:target="save"', 'wire:loading', 'motion-safe:animate-spin', 'aria-hidden="true"', 'sr-only', 'Loading…');
});

it('targets a submit action explicitly without reading its parent form', function () {
    $html = Blade::render('<form wire:submit="update"><x-ui.button type="submit" target="update">Save</x-ui.button></form>');
    expect($html)->toContain('wire:loading.attr="disabled"', 'wire:target="update"', 'wire:loading.inline-flex');
});

it('preserves upload targets and navigation links', function () {
    $html = Blade::render('<x-ui.button type="submit" wire:target="save,image" wire:loading.attr="disabled">Save</x-ui.button>');
    expect($html)->toContain('wire:target="save,image"');
    $link = Blade::render('<x-ui.button href="/account">Account</x-ui.button>');
    expect($link)->toContain('wire:navigate')->not->toContain('wire:loading', 'spinner');
    expect(Blade::render('<x-ui.button x-on:click="open = true">Open</x-ui.button>'))->not->toContain('wire:loading');
});
