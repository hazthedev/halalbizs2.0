<?php

use App\Models\Page;
use Database\Seeders\PageSeeder;

it('renders a seeded static page with its title', function () {
    $this->seed(PageSeeder::class);

    $this->get('/page/about')
        ->assertOk()
        ->assertSee('About Us');
});

it('returns 404 for an unknown page', function () {
    $this->get('/page/unknown')->assertNotFound();
});

it('returns 404 for an inactive page', function () {
    Page::create([
        'slug' => 'draft',
        'title' => ['en' => 'Draft Page'],
        'body' => ['en' => '<p>Not yet published.</p>'],
        'is_active' => false,
    ]);

    $this->get('/page/draft')->assertNotFound();
});
