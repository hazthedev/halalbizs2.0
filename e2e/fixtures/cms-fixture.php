<?php

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\ScheduledLayout;
use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

try {
    $root = getenv('HB_E2E_APP_ROOT') ?: getcwd();
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $connection = config('database.connections.'.config('database.default'));
    if (! app()->environment('local') || ! in_array($connection['host'] ?? '', ['127.0.0.1', 'localhost'], true) || ($connection['database'] ?? '') !== 'hbiz2_qa') {
        throw new RuntimeException('CMS fixtures require the local QA database.');
    }
    $data = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $run = $data['run'];
    if (! preg_match('/^e2e-[0-9]+-[a-f0-9]+$/', $run)) {
        throw new InvalidArgumentException('Invalid fixture identifier.');
    }
    if (($argv[1] ?? '') === 'create') {
        foreach (['publisher', 'editor', 'buyer'] as $role) {
            $user = User::create(['name' => $run.' '.$role, 'email' => $run.'-'.$role.'@halalbizs.test', 'password' => $data['password']]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole($role === 'buyer' ? 'buyer' : 'admin');
            if ($role !== 'buyer') {
                $user->syncPermissions($role === 'publisher' ? ['cms.manage', 'cms.publish'] : ['cms.manage']);
            }
        }
        $buyer = User::where('email', $run.'-buyer@halalbizs.test')->firstOrFail();
        $store = Store::factory()->approved()->create(['user_id' => $buyer->id, 'name' => $run.' store']);
        $category = Category::factory()->create(['name' => ['en' => $run.' category']]);
        Product::factory()->create(['store_id' => $store->id, 'category_id' => $category->id, 'name' => ['en' => $run.' product']]);
    } elseif (($argv[1] ?? '') === 'cleanup') {
        Page::where('slug', $run)->get()->each(function ($page) {
            $page->draft?->delete();
            $page->versions()->get()->each->delete();
            $page->delete();
        });
        ScheduledLayout::where('name', $run)->get()->each(function ($layout) {
            $layout->sections->each(function ($section) {
                $section->draft?->delete();
                $section->versions()->get()->each->delete();
            });
            $layout->delete();
        });
        Store::where('name', $run.' store')->get()->each(function ($store) {
            $store->products()->withTrashed()->get()->each->forceDelete();
            $store->forceDelete();
        });
        Category::where('name->en', $run.' category')->get()->each->delete();
        User::whereIn('email', array_map(fn ($role) => $run.'-'.$role.'@halalbizs.test', ['publisher', 'editor', 'buyer']))->get()->each(function ($user) {
            $user->syncRoles([]);
            $user->syncPermissions([]);
            $user->forceDelete();
        });
    } else {
        throw new InvalidArgumentException('Unknown action.');
    }

} catch (Throwable $error) {
    fwrite(STDERR, "CMS fixture failed.\n");
    exit(1);
}
