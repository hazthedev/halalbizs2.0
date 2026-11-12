<?php

namespace App\Http\Controllers\Api;

use App\Enums\HelpCategory;
use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Models\Page;
use Illuminate\Http\JsonResponse;

/**
 * Admin-written pages and help articles as data (Admin → Content → Pages,
 * Support → Articles). Bodies were cleaned by HtmlSanitizer when saved, the
 * same HTML the web prints.
 */
class ContentController extends Controller
{
    public function pages(): JsonResponse
    {
        return ApiResponse::ok(Page::active()->orderBy('id')->get()->map(fn (Page $page) => [
            'slug' => $page->slug,
            'title' => $page->getTranslation('title', app()->getLocale()),
        ])->all());
    }

    public function page(string $slug): JsonResponse
    {
        $page = Page::active()->where('slug', $slug)->firstOrFail();

        return ApiResponse::ok([
            'slug' => $page->slug,
            'title' => $page->getTranslation('title', app()->getLocale()),
            'body' => $page->getTranslation('body', app()->getLocale()),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ]);
    }

    public function help(): JsonResponse
    {
        $order = array_flip(array_column(HelpCategory::cases(), 'value'));

        return ApiResponse::ok(HelpArticle::active()->orderBy('position')->orderBy('id')->get()
            ->sortBy(fn (HelpArticle $article) => $order[$article->category->value], SORT_REGULAR, false)
            ->values()
            ->map(fn (HelpArticle $article) => $this->helpRow($article))
            ->all());
    }

    public function helpArticle(int $article): JsonResponse
    {
        $article = HelpArticle::active()->findOrFail($article);

        return ApiResponse::ok($this->helpRow($article) + [
            'body' => $article->getTranslation('body', app()->getLocale()),
            'updated_at' => $article->updated_at?->toIso8601String(),
        ]);
    }

    /** @return array{id: int, category: string, category_label: string, title: string} */
    private function helpRow(HelpArticle $article): array
    {
        return [
            'id' => $article->id,
            'category' => $article->category->value,
            'category_label' => $article->category->label(),
            'title' => $article->getTranslation('title', app()->getLocale()),
        ];
    }
}
