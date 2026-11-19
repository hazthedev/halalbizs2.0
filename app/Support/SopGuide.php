<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * HB-SOP: the user guides in resources/sop/{guide}.{locale}.md, rendered for
 * the help pages. Raw HTML is stripped, which also drops the reviewer's
 * CHECK/SHOT comments, so nothing in a guide file can reach the page as markup.
 */
final class SopGuide
{
    /** Guides anyone may read. The admin guide lives behind /admin. */
    public const PUBLIC = ['buyer', 'seller'];

    /**
     * Vietnamese has its own files; every other locale (Malay included,
     * none written yet) reads the English one.
     *
     * @return array{title: string, toc: list<array{id: string, title: string}>, html: string}
     */
    public static function load(string $guide, ?string $locale = null): array
    {
        abort_unless(in_array($guide, [...self::PUBLIC, 'admin'], true), 404);
        $lang = ($locale ?? app()->getLocale()) === 'vi' ? 'vi' : 'en';

        // ponytail: rendered on every request (six small files, a few ms); cache on mtime if it ever shows up in a profile.
        return self::render(file_get_contents(resource_path("sop/{$guide}.{$lang}.md")));
    }

    /** @return array{title: string, toc: list<array{id: string, title: string}>, html: string} */
    public static function render(string $markdown): array
    {
        // The "# Title" line becomes the page heading, so it leaves the body.
        $title = preg_match('/^#\s+(.+)$/m', $markdown, $m) ? trim($m[1]) : '';
        $body = preg_replace('/^#\s+.+$/m', '', $markdown, 1);

        $html = (string) Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        $toc = [];
        $html = preg_replace_callback('/<h2>(.*?)<\/h2>/s', function (array $h) use (&$toc) {
            $id = 'section-'.(count($toc) + 1);
            $toc[] = ['id' => $id, 'title' => html_entity_decode(strip_tags($h[1]), ENT_QUOTES | ENT_HTML5)];

            return "<h2 id=\"{$id}\">{$h[1]}</h2>";
        }, $html);

        return ['title' => $title, 'toc' => $toc, 'html' => $html];
    }
}
