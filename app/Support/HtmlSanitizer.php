<?php

namespace App\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMProcessingInstruction;

/**
 * Dependency-free rich-text sanitizer (C2, security audit) built on ext-dom's
 * DOMDocument — no new Composer package. Used for product descriptions, which
 * are the only free-text field allowed to carry markup.
 *
 * Contract: ONLY <p><br><ul><ol><li><strong><em> survive, and every attribute
 * on them is stripped (the app needs zero attributes on these tags, so "strip
 * everything" is safe and simple — no attribute allow-list to get wrong).
 * Every other tag (including <script>, <style>, event-handler-bearing tags,
 * and anything else) is unwrapped: the tag itself is dropped but its text
 * content is preserved, matching the pre-existing strip_tags() behaviour this
 * class replaces so translations keep reading naturally.
 */
final class HtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = ['p', 'br', 'ul', 'ol', 'li', 'strong', 'em'];

    public static function clean(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument;
        $previousSetting = libxml_use_internal_errors(true);

        // The "<?xml encoding="UTF-8">" prefix tells libxml the true encoding
        // without emitting a real node, so Malay/Chinese text survives intact.
        // LIBXML_HTML_NOIMPLIED|NODEFDTD stop libxml wrapping the fragment in
        // its own <html><body>, so we can pull the content back out cleanly.
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        $root = $document->getElementsByTagName('div')->item(0);

        if ($root === null) {
            return '';
        }

        self::sanitizeChildren($root);

        $result = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    private static function sanitizeChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                self::sanitizeChildren($child);

                if (in_array(strtolower($child->tagName), self::ALLOWED_TAGS, true)) {
                    foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                        $child->removeAttribute($attribute->name);
                    }
                } else {
                    // Disallowed tag: unwrap it — keep its children/text, drop the tag.
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }

                    $node->removeChild($child);
                }
            } elseif ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                $node->removeChild($child);
            }

            // DOMText/DOMCharacterData nodes are left untouched — text content is preserved.
        }
    }
}
