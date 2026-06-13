<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Livewire\Livewire;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // #[Lazy] Livewire children (PDP reviews/related strips) render
        // inline in tests so page-level assertions keep seeing their
        // content. Livewire resets this flag between tests.
        Livewire::withoutLazyLoading();
    }
}
