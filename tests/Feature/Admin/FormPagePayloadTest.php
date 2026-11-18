<?php

use App\Livewire\Admin\Content\Banners\Form as BannersForm;
use App\Livewire\Admin\Content\Pages\Form as PagesForm;
use App\Livewire\Admin\Content\Vouchers\Form as VouchersForm;
use App\Livewire\Admin\Support\Articles\Form as ArticlesForm;
use App\Models\Banner;
use App\Models\HelpArticle;
use App\Models\Page;
use App\Models\Voucher;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(contentPublisher());
    Storage::fake('public');
});

test('content form preserves its create and edit draft payload and files', function (string $component, string $model, string $parameter, array $fields, array $expected, ?string $media) {
    $form = Livewire::test($component);
    foreach ($fields as $field => $value) {
        $form->set($field, $value);
    }
    if ($media !== null) {
        $form->set($media, UploadedFile::fake()->image('page.jpg', 1500, 500));
    }
    $form->call('save')->assertHasNoErrors()->assertRedirect(route('admin.'.($parameter === 'article' ? 'support.articles' : 'content.'.($parameter === 'page' ? 'pages' : 'banners'))));
    $record = $model::sole();
    $payload = $record->draft->payload;
    foreach ($expected as $field => $value) {
        $actual = $payload[$field];
        expect(is_array($value) ? json_decode($actual, true) : $actual)->toBe($value);
    }
    expect(array_keys($payload))->toEqualCanonicalizing(array_keys($expected));
    expect($record->is_active)->toBeFalse();
    $live = $record->getRawOriginal();
    $fileId = $media === null ? null : $record->draft->getFirstMedia($media === 'shareImage' ? 'share_image' : 'image')->id;

    Livewire::test($component, [$parameter => $record])->assertSet('title.en', 'Form payload')
        ->set('title.en', 'Edited payload')->call('save')->assertHasNoErrors()->assertRedirect();
    $this->get(route('admin.'.($parameter === 'article' ? 'support.articles' : 'content.'.($parameter === 'page' ? 'pages' : 'banners'))))->assertSee('Edited payload')->assertSee(__('Saved as a draft. It goes live when published.'));
    $record->refresh();
    $edited = $record->draft->payload;
    expect(json_decode($edited['title'], true)['en'])->toBe('Edited payload');
    unset($payload['title'], $edited['title']);
    ksort($edited);
    ksort($payload);
    expect($edited)->toBe($payload)->and($record->getRawOriginal())->toBe($live);
    if ($media !== null) {
        expect($record->draft->getFirstMedia($media === 'shareImage' ? 'share_image' : 'image')->id)->toBe($fileId);
    }
})->with([
    'banner' => [BannersForm::class, Banner::class, 'banner', [
        'title.en' => 'Form payload', 'title.ms' => 'Tajuk', 'title.vi' => 'Viet',
        'subtitle.en' => 'Subtitle', 'ctaLabel.en' => 'Shop now', 'destination' => 'custom', 'linkUrl' => '/help',
        'startsAt' => '2026-11-18T10:00', 'endsAt' => '2026-11-25T18:00',
    ], [
        'position' => 1, 'link_url' => '/help', 'starts_at' => '2026-11-18 02:00:00', 'ends_at' => '2026-11-25 10:00:00', 'is_active' => true,
        'title' => ['en' => 'Form payload', 'ms' => 'Tajuk', 'vi' => 'Viet'], 'subtitle' => ['en' => 'Subtitle'], 'cta_label' => ['en' => 'Shop now'],
    ], 'image'],
    'page' => [PagesForm::class, Page::class, 'page', [
        'slug' => 'payload-page', 'title.en' => 'Form payload', 'title.ms' => 'Tajuk', 'title.vi' => 'Viet',
        'body.en' => '<p>Safe</p><script>bad()</script>', 'body.ms' => '<p>BM</p>', 'body.vi' => '<p>VI</p>',
        'seoTitle.en' => 'Share title', 'seoDescription.en' => 'Share words', 'showInFooter' => true,
    ], [
        'slug' => 'payload-page', 'is_active' => true, 'show_in_footer' => true,
        'title' => ['en' => 'Form payload', 'ms' => 'Tajuk', 'vi' => 'Viet'], 'body' => ['en' => '<p>Safe</p>', 'ms' => '<p>BM</p>', 'vi' => '<p>VI</p>'],
        'seo_title' => ['en' => 'Share title'], 'seo_description' => ['en' => 'Share words'],
    ], 'shareImage'],
    'article' => [ArticlesForm::class, HelpArticle::class, 'article', [
        'category' => 'selling', 'position' => '7', 'title.en' => 'Form payload', 'title.ms' => 'Tajuk', 'title.vi' => 'Viet',
        'body.en' => '<p>Safe</p><script>bad()</script>', 'body.ms' => '<p>BM</p>', 'body.vi' => '<p>VI</p>',
    ], [
        'category' => 'selling', 'position' => 7, 'is_active' => true,
        'title' => ['en' => 'Form payload', 'ms' => 'Tajuk', 'vi' => 'Viet'], 'body' => ['en' => '<p>Safe</p>', 'ms' => '<p>BM</p>', 'vi' => '<p>VI</p>'],
    ], null],
]);

test('platform voucher preserves its complete create and edit payload', function () {
    Livewire::test(VouchersForm::class)->set('code', 'page10')->set('type', 'percent')->set('percent', '12.50')
        ->set('maxDiscount', '20.25')->set('minSpend', '50.00')->set('quota', '20')->set('perUserLimit', '2')->set('isActive', false)
        ->set('startsAt', '2026-11-18T10:00')->set('endsAt', '2026-11-25T18:00')->call('save')->assertHasNoErrors()->assertRedirect(route('admin.content.vouchers'));
    $voucher = Voucher::sole();
    $expected = [
        'scope' => 'platform', 'store_id' => null, 'code' => 'PAGE10', 'type' => 'percent', 'value_sen' => null,
        'percent' => '12.50', 'max_discount_sen' => 2025, 'min_spend_sen' => 5000, 'quota' => 20,
        'per_user_limit' => 2, 'is_active' => 0, 'used_count' => 0,
        'starts_at' => '2026-11-18 02:00:00', 'ends_at' => '2026-11-25 10:00:00',
    ];
    expect($voucher->getRawOriginal())->toMatchArray($expected);
    Livewire::test(VouchersForm::class, ['voucher' => $voucher->id])->set('percent', '15')->call('save')->assertHasNoErrors()->assertRedirect(route('admin.content.vouchers'));
    expect($voucher->fresh()->getRawOriginal())->toMatchArray([...$expected, 'percent' => '15.00']);
    $this->get(route('admin.content.vouchers'))->assertSee('PAGE10')->assertSee(__('Voucher updated'));
});
