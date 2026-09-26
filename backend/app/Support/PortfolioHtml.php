<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list sanitizer for student-authored portfolio rich text.
 *
 * Only basic formatting survives: paragraphs, headings, bold/italic/underline,
 * lists, line breaks, and text-align. Every other tag is unwrapped (its text is
 * kept) and every attribute except a whitelisted text-align style is dropped, so
 * the HTML is safe to render for faculty and coordinators.
 */
final class PortfolioHtml
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'div',
    ];

    /** Tags whose content is dropped entirely rather than unwrapped. */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math'];

    private const ALIGNMENTS = ['left', 'center', 'right', 'justify'];

    public static function sanitize(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"?><div id="__root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');
        if (! $root) {
            return '';
        }

        self::cleanChildren($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return self::isBlank($out) ? '' : trim($out);
    }

    /** True when the HTML has no visible text. */
    public static function isBlank(?string $html): bool
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $text)) === '';
    }

    private static function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }
            if (! $child instanceof DOMElement) {
                $node->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }

            self::cleanChildren($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            $align = self::alignment($child->getAttribute('style'));
            foreach (iterator_to_array($child->attributes) as $attr) {
                $child->removeAttribute($attr->nodeName);
            }
            if ($align !== null) {
                $child->setAttribute('style', 'text-align: '.$align.';');
            }
        }
    }

    private static function alignment(string $style): ?string
    {
        if (preg_match('/text-align\s*:\s*([a-z]+)/i', $style, $m)) {
            $value = strtolower($m[1]);

            return in_array($value, self::ALIGNMENTS, true) ? $value : null;
        }

        return null;
    }
}
