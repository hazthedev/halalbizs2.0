<?php

use App\Enums\HelpCategory;
use App\Models\HelpArticle;
use App\Models\Page;

// CMS item 5: pages and help articles as data, so the app can show them as
// its own screens and keep a copy for offline reading.

test('pages list active pages and one page carries its body in the asked language', function () {
    Page::create(['slug' => 'terms', 'title' => ['en' => 'Terms', 'ms' => 'Terma'], 'body' => ['en' => '<p>English terms</p>', 'ms' => '<p>Terma BM</p>'], 'is_active' => true]);
    Page::create(['slug' => 'old', 'title' => ['en' => 'Old'], 'body' => ['en' => '<p>x</p>'], 'is_active' => false]);

    $this->getJson('/api/v1/pages')
        ->assertOk()
        ->assertJsonPath('data', [['slug' => 'terms', 'title' => 'Terms']]);

    $this->getJson('/api/v1/pages/terms', ['Accept-Language' => 'ms'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Terma')
        ->assertJsonPath('data.body', '<p>Terma BM</p>')
        ->assertJsonStructure(['data' => ['slug', 'title', 'body', 'updated_at']]);

    $this->getJson('/api/v1/pages/old')->assertNotFound();
    $this->getJson('/api/v1/pages/nope')->assertNotFound();
});

test('help lists active articles by category then position, and one article carries its body', function () {
    $second = HelpArticle::create(['category' => HelpCategory::Buying, 'title' => ['en' => 'Track an order'], 'body' => ['en' => '<p>b</p>'], 'position' => 2, 'is_active' => true]);
    $first = HelpArticle::create(['category' => HelpCategory::Buying, 'title' => ['en' => 'Place an order'], 'body' => ['en' => '<p>a</p>'], 'position' => 1, 'is_active' => true]);
    $hidden = HelpArticle::create(['category' => HelpCategory::Buying, 'title' => ['en' => 'Hidden'], 'body' => ['en' => '<p>h</p>'], 'position' => 0, 'is_active' => false]);

    $this->getJson('/api/v1/help')
        ->assertOk()
        ->assertJsonPath('data', [
            ['id' => $first->id, 'category' => 'buying', 'category_label' => 'Buying', 'title' => 'Place an order'],
            ['id' => $second->id, 'category' => 'buying', 'category_label' => 'Buying', 'title' => 'Track an order'],
        ]);

    $this->getJson("/api/v1/help/{$first->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Place an order')
        ->assertJsonPath('data.body', '<p>a</p>');

    $this->getJson("/api/v1/help/{$hidden->id}")->assertNotFound();
});
