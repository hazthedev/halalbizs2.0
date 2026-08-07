<?php

namespace App\Support;

use DOMCdataSection;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMProcessingInstruction;

/**
 * Dependency-free rich-text sanitizer (C2, security audit) built on ext-dom's
 * DOMDocument — no new Composer package. Used for product descriptions and,
 * with the wider CMS_TAGS list, for admin-authored CMS/help bodies.
 *
 * Contract: ONLY the allowed tags survive, and every attribute on them is
 * stripped (the app needs zero attributes on these tags, so "strip
 * everything" is safe and simple — no attribute allow-list to get wrong). The
 * one exception is <a href>, which a CMS body is useless without; its URL is
 * scheme-checked. Every other tag (including <script>, <style>,
 * event-handler-bearing tags, and anything else) is unwrapped: the tag itself
 * is dropped but its text content is preserved, matching the pre-existing
 * strip_tags() behaviour this class replaces so translations keep reading
 * naturally. Raw-text elements are the exception — their body goes with them,
 * see the CDATA note in sanitizeChildren().
 */
final class HtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = ['p', 'br', 'ul', 'ol', 'li', 'strong', 'em'];

    /** Admin-authored CMS pages and help articles — headings and links too. */
    public const CMS_TAGS = ['p', 'br', 'h2', 'h3', 'ul', 'ol', 'li', 'strong', 'em', 'a'];

    /**
     * @param  list<string>  $allowedTags
     */
    public static function clean(string $html, array $allowedTags = self::ALLOWED_TAGS): string
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
            return self::textFallback($html);
        }

        self::sanitizeChildren($root, $allowedTags);

        $result = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $result .= $document->saveHTML($child);
        }

        $result = trim($result);

        // Non-blank in, blank out means the parse lost the content rather than
        // rejecting it: a body whose first tag is a stray CLOSING tag closes the
        // wrapper above, and everything after it lands outside $root and is
        // dropped. That silently overwrote a live CMS page with an empty string
        // while the admin was told "Page updated" — and `required` could not
        // catch it, because validation runs on the RAW input, before this.
        //
        // No wrapper tag is immune (switching to <body> only moves the trigger
        // to </body>), so recover instead: keep the author's words as escaped
        // plain text and lose only the formatting. That matches what this class
        // already does with a disallowed tag — drop the tag, keep the text.
        if ($result === '' && self::textFallback($html) !== '') {
            return self::textFallback($html);
        }

        return $result;
    }

    /**
     * Escaped plain text — no markup at all, so always safe to emit.
     *
     * Raw-text element bodies are removed rather than kept, mirroring the main
     * path: C1's fix drops them WITH their tag on purpose, so a fallback that
     * handed `<script>alert(1)</script>` back as the visible text "alert(1)"
     * would quietly undo that decision.
     */
    private static function textFallback(string $html): string
    {
        $rawText = 'script|style|noembed|noframes|iframe|plaintext|xmp|textarea|title';

        // Closed pairs first, then any unclosed remainder (<plaintext> rarely closes).
        $html = preg_replace('#<('.$rawText.')\b[^>]*>.*?</\1\s*>#is', '', $html) ?? $html;
        $html = preg_replace('#<('.$rawText.')\b[^>]*>.*#is', '', $html) ?? $html;

        return trim(htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    }

    /**
     * @param  list<string>  $allowedTags
     */
    private static function sanitizeChildren(DOMNode $node, array $allowedTags): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                self::sanitizeChildren($child, $allowedTags);

                if (in_array(strtolower($child->tagName), $allowedTags, true)) {
                    $isLink = strtolower($child->tagName) === 'a';

                    foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                        if ($isLink && strtolower($attribute->name) === 'href' && self::isSafeUrl($attribute->value)) {
                            continue;
                        }

                        $child->removeAttribute($attribute->name);
                    }
                } else {
                    // Disallowed tag: unwrap it — keep its children/text, drop the tag.
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }

                    $node->removeChild($child);
                }
            } elseif ($child instanceof DOMCdataSection || $child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                $node->removeChild($child);
            }

            // DOMText nodes are left untouched — text content is preserved.
            // CDATA is NOT: libxml parses raw-text element bodies (<script>,
            // <style>, <noembed>, <plaintext>…) into CDATA, and saveHTML()
            // serialises CDATA WITHOUT escaping, so unwrapping the tag handed
            // the payload back as live markup (C1). Dropping the body outright
            // rather than escaping it also covers the nested <svg><style> case,
            // where the escaped text would sit in foreign content.
        }
    }

    /** Browsers strip whitespace/control chars inside a scheme, so "java\tscript:" runs. */
    private static function isSafeUrl(string $url): bool
    {
        $url = preg_replace('/[\s\x00-\x1F\x7F]+/', '', $url) ?? '';

        return preg_match('#^(?:https?://|mailto:|tel:|/|\#)#i', $url) === 1;
    }
}
