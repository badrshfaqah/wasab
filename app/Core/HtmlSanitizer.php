<?php

namespace App\Core;

/**
 * تنقية HTML قادم من محرر نصي غني (contenteditable) قبل حفظه أو عرضه.
 *
 * المحتوى يُدخله مستخدمون بصلاحية "إنشاء/تعديل" فقط (ليسوا بالضرورة مدراء)،
 * ويُعرض لاحقاً لمستخدمين آخرين (معتمدين، مشاهدين) - فبدون تنقية صارمة على
 * الخادم يصبح أي حقل نص غني ثغرة XSS مخزّنة تتيح تصعيد صلاحيات فعلياً.
 * تعتمد على DOMDocument فقط (بدون مكتبات خارجية، لا Composer).
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'div', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'span',
        'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'blockquote',
        'table', 'thead', 'tbody', 'tr', 'td', 'th', 'a', 'hr',
    ];

    private const ALLOWED_ATTRS = [
        'style', 'href', 'target', 'colspan', 'rowspan', 'dir',
    ];

    private const ALLOWED_STYLE_PROPS = [
        'text-align', 'font-weight', 'font-style', 'text-decoration',
        'color', 'background-color', 'font-size',
    ];

    public static function sanitize(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="utf-8" ?><div id="__root__">' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();

        $root = $doc->getElementById('__root__');
        if (!$root) {
            return '';
        }

        self::cleanNode($doc, $root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private static function cleanNode(\DOMDocument $doc, \DOMNode $node): void
    {
        $children = iterator_to_array($node->childNodes);
        foreach ($children as $child) {
            if ($child instanceof \DOMComment) {
                $node->removeChild($child);
                continue;
            }

            if ($child instanceof \DOMText) {
                continue;
            }

            if (!$child instanceof \DOMElement) {
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                // إحلال العنصر غير المسموح بمحتواه النصي فقط (بدون الوسم نفسه)
                self::cleanNode($doc, $child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            self::sanitizeAttributes($child);
            self::cleanNode($doc, $child);
        }
    }

    private static function sanitizeAttributes(\DOMElement $el): void
    {
        $attrs = iterator_to_array($el->attributes ?? []);
        foreach ($attrs as $attr) {
            $name = strtolower($attr->name);

            if (!in_array($name, self::ALLOWED_ATTRS, true)) {
                $el->removeAttribute($attr->name);
                continue;
            }

            if ($name === 'href') {
                $value = trim($attr->value);
                if (!preg_match('~^(https?://|/|#)~i', $value)) {
                    $el->removeAttribute('href');
                } else {
                    $el->setAttribute('rel', 'noopener noreferrer');
                }
                continue;
            }

            if ($name === 'style') {
                $el->setAttribute('style', self::sanitizeStyle($attr->value));
            }
        }
    }

    private static function sanitizeStyle(string $style): string
    {
        $safe = [];
        foreach (explode(';', $style) as $decl) {
            $parts = explode(':', $decl, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $prop = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if (!in_array($prop, self::ALLOWED_STYLE_PROPS, true)) {
                continue;
            }
            if (preg_match('/url\s*\(|expression\s*\(|javascript:/i', $value)) {
                continue;
            }
            $safe[] = $prop . ':' . $value;
        }
        return implode(';', $safe);
    }
}
