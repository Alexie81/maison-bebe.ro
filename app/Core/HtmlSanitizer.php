<?php
declare(strict_types=1);

namespace MaisonBebe\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

final class HtmlSanitizer
{
    private const TAGS = ['p','div','span','h2','h3','h4','ul','ol','li','strong','b','em','i','u','s','blockquote','a','br','hr','figure','figcaption','img','table','thead','tbody','tr','th','td','pre','code'];
    private const ATTRS = [
        'a'=>['href','title','rel','target','style'],
        'img'=>['src','alt','width','height','loading','style'],
        'table'=>['style'],'thead'=>['style'],'tbody'=>['style'],'tr'=>['style'],'th'=>['style'],'td'=>['style'],
        'p'=>['style'],'div'=>['style'],'span'=>['style'],'h2'=>['style'],'h3'=>['style'],'h4'=>['style'],
        'strong'=>['style'],'b'=>['style'],'em'=>['style'],'i'=>['style'],'u'=>['style'],'s'=>['style'],
        'blockquote'=>['style'],'pre'=>['style'],'code'=>['style'],'figure'=>['style'],'figcaption'=>['style'],'ul'=>['style'],'ol'=>['style'],'li'=>['style'],
    ];
    private const STYLE_PROPERTIES = ['color','background-color','text-align','font-size','font-weight','font-style','text-decoration','line-height','font-family','width','max-width','margin-left','margin-right','border-radius'];

    public static function clean(string $html): string
    {
        if (trim(strip_tags($html, '<img><br><hr>')) === '' && !preg_match('/<(img|br|hr)\b/i', $html)) {
            return '';
        }
        $document = new DOMDocument('1.0','UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $document->getElementById('root');
        if (!$root) {
            return '';
        }
        self::walk($root);
        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }
        return trim($output);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (!in_array($tag, self::TAGS, true)) {
                if (in_array($tag, ['script','style','iframe','object','embed','template','noscript'], true)) {
                    $node->removeChild($child);
                    continue;
                }
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                if (!in_array($name, self::ATTRS[$tag] ?? [], true)) {
                    $child->removeAttribute($attribute->name);
                }
            }
            if ($child->hasAttribute('style')) {
                $style = self::cleanStyle($child->getAttribute('style'));
                $style === '' ? $child->removeAttribute('style') : $child->setAttribute('style', $style);
            }
            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                if (!preg_match('~^(?:https?://|/|mailto:|tel:|#)~i', $href)) {
                    $child->removeAttribute('href');
                } else {
                    $child->setAttribute('rel', 'noopener noreferrer');
                    if (preg_match('#^https?://#i', $href)) {
                        $child->setAttribute('target', '_blank');
                    }
                }
            }
            if ($tag === 'img') {
                $src = trim($child->getAttribute('src'));
                if (!str_starts_with($src, '/') && !preg_match('#^https://#i', $src)) {
                    $child->removeAttribute('src');
                }
                $child->setAttribute('loading', 'lazy');
                if (!$child->hasAttribute('alt')) {
                    $child->setAttribute('alt', '');
                }
            }
            self::walk($child);
        }
    }

    private static function cleanStyle(string $style): string
    {
        $safe = [];
        foreach (explode(';', $style) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }
            [$property,$value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $value = trim($value);
            if ($property === 'font-family') {
                $value = str_replace(['"', "'"], '', $value);
            }
            if (!in_array($property, self::STYLE_PROPERTIES, true) || $value === '') {
                continue;
            }
            if (preg_match('/url\s*\(|expression|javascript|behavior|@import|[{}<>]/i', $value)) {
                continue;
            }
            if (!preg_match('/^[#(),.%\-\s\w]+$/u', $value)) {
                continue;
            }
            $safe[] = $property . ':' . mb_substr($value, 0, 80);
        }
        return implode(';', $safe);
    }
}