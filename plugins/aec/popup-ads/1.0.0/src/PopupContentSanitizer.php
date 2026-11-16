<?php

namespace Aloms\PopupAds;

use DOMDocument;
use DOMElement;
use DOMNode;

final class PopupContentSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u',
        'ul', 'ol', 'li', 'h3', 'h4', 'blockquote', 'a', 'span',
    ];

    private const DROP_WITH_CONTENT_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet',
        'svg', 'math', 'form', 'input', 'button', 'textarea',
        'select', 'option', 'link', 'meta', 'base', 'template',
        'noscript', 'canvas', 'video', 'audio', 'source',
    ];

    public static function sanitizeHtml(?string $html): ?string
    {
        if (! is_string($html)) {
            return null;
        }

        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $html) ?? '';
        $cleaned = trim($cleaned);
        if ($cleaned === '') {
            return null;
        }

        if (! class_exists(DOMDocument::class)) {
            return e(strip_tags($cleaned));
        }

        $previousErrorSetting = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="__popup_root">'.$cleaned.'</div></body></html>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorSetting);

        $root = $dom->getElementById('__popup_root');
        if (! $root instanceof DOMElement) {
            return null;
        }

        self::sanitizeChildren($root, $dom);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        $output = trim($output);

        return $output !== '' ? $output : null;
    }

    private static function sanitizeChildren(DOMNode $parent, DOMDocument $dom): void
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($child);
                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT_TAGS, true)) {
                $parent->removeChild($child);
                continue;
            }

            self::sanitizeChildren($child, $dom);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild !== null) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);
                continue;
            }

            $href = $tag === 'a' ? $child->getAttribute('href') : '';
            $target = $tag === 'a' ? $child->getAttribute('target') : '';

            $attributeNames = [];
            foreach ($child->attributes as $attribute) {
                $attributeNames[] = $attribute->name;
            }
            foreach ($attributeNames as $attributeName) {
                $child->removeAttribute($attributeName);
            }

            if ($tag === 'a') {
                $safeHref = self::normalizeSafeUrl($href);
                if ($safeHref === null) {
                    $span = $dom->createElement('span');
                    while ($child->firstChild !== null) {
                        $span->appendChild($child->firstChild);
                    }
                    $parent->replaceChild($span, $child);
                    continue;
                }

                $child->setAttribute('href', $safeHref);
                $child->setAttribute('rel', 'noopener noreferrer');
                if ($target === '_blank' || str_starts_with(strtolower($safeHref), 'https://')) {
                    $child->setAttribute('target', '_blank');
                }
            }
        }
    }

    public static function normalizeSafeUrl(?string $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $cleaned = preg_replace('/[\x00-\x20\x7F]+/u', '', trim($url)) ?? '';
        if ($cleaned === '' || mb_strlen($cleaned) > 500) {
            return null;
        }

        if (str_starts_with($cleaned, '//') || str_contains($cleaned, '\\')) {
            return null;
        }

        if (str_starts_with($cleaned, '/')) {
            return preg_match('/^\/[A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]*$/', $cleaned) === 1
                ? $cleaned
                : null;
        }

        if (! preg_match('/^https:\/\//i', $cleaned)) {
            return null;
        }

        $parts = parse_url($cleaned);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }

        if (empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return filter_var($cleaned, FILTER_VALIDATE_URL) !== false ? $cleaned : null;
    }

    public static function sanitizeHexColor(?string $color, string $default): string
    {
        $candidate = is_string($color) ? trim($color) : '';

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $candidate) === 1
            ? strtolower($candidate)
            : strtolower($default);
    }

    public static function normalizePathPatterns(?string $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $tokens = preg_split('/[\r\n,]+/', $raw) ?: [];
        $normalized = [];

        foreach ($tokens as $token) {
            $item = preg_replace('/[\x00-\x1F\x7F]/', '', trim($token)) ?? '';
            if ($item === '') {
                continue;
            }

            $negated = str_starts_with($item, '!');
            $path = $negated ? substr($item, 1) : $item;
            $path = trim($path);

            if ($path === '' || str_contains($path, ' ') || str_contains($path, '\\') || str_contains($path, '..')) {
                continue;
            }

            if (! str_starts_with($path, '/')) {
                $path = '/'.$path;
            }

            if (str_starts_with($path, '//')) {
                continue;
            }

            $entry = ($negated ? '!' : '').$path;
            if (! in_array($entry, $normalized, true)) {
                $normalized[] = $entry;
            }
        }

        return $normalized !== [] ? implode("\n", array_slice($normalized, 0, 40)) : null;
    }
}
