<?php

namespace App\Support\Blog;

/**
 * A52 · Галереи в тексте публикации.
 *
 * Единый формат: <figure data-gallery="grid|grid-2|grid-3|masonry|slider"> <figure><img><figcaption?></figure> … <figcaption?> </figure>.
 * Старые галереи WordPress приводятся к нему же:
 *  — figure > figure (WordPress 5.9+),
 *  — figure > ul > li > figure (WordPress 5.0–5.8, «blocks-gallery-grid» — на витрине шли столбиком с маркерами списка).
 * Нормализация идемпотентна: её делает HtmlSanitizer при сохранении и PostRenderer при показе (для уже сохранённых текстов).
 */
class Gallery
{
    /** grid — «авто»: число колонок от количества картинок. */
    public const LAYOUTS = ['grid', 'grid-2', 'grid-3', 'masonry', 'slider'];

    public static function layout(?string $value): string
    {
        return in_array($value, self::LAYOUTS, true) ? $value : 'grid';
    }

    /** Привести галереи внутри $root к единому формату. $render — добавить data-count для CSS (на витрине). */
    public static function normalize(\DOMElement $root, bool $render = false): void
    {
        // снизу вверх: вложенные figure обрабатываются раньше внешних
        $figures = array_reverse(iterator_to_array($root->getElementsByTagName('figure')));
        foreach ($figures as $fig) {
            if (! $fig->parentNode) {
                continue;
            }
            $items = [];
            $caption = null;
            $foreign = false;
            foreach (iterator_to_array($fig->childNodes) as $child) {
                if ($child instanceof \DOMText && trim($child->textContent) === '') {
                    continue;
                }
                if (! $child instanceof \DOMElement) {
                    $foreign = true;

                    continue;
                }
                $tag = strtolower($child->tagName);
                if ($tag === 'figure' && self::isImageFigure($child)) {
                    $items[] = $child;
                } elseif ($tag === 'figcaption') {
                    $caption = $child;
                } elseif (in_array($tag, ['ul', 'ol'], true) && ($listed = self::listFigures($child)) !== null) {
                    array_push($items, ...$listed);
                } else {
                    $foreign = true;
                }
            }
            $marked = $fig->hasAttribute('data-gallery');
            if ($foreign || ! $items || (count($items) < 2 && ! $marked)) {
                continue;
            }

            // пересобираем: только элементы галереи и общая подпись в конце
            while ($fig->firstChild) {
                $fig->removeChild($fig->firstChild);
            }
            foreach ($items as $item) {
                foreach (iterator_to_array($item->attributes) as $a) {
                    $item->removeAttribute($a->name);
                }
                $fig->appendChild($item);
            }
            if ($caption && trim($caption->textContent) !== '') {
                $fig->appendChild($caption);
            }
            $fig->setAttribute('data-gallery', self::layout($fig->getAttribute('data-gallery')));
            if ($render) {
                $fig->setAttribute('data-count', (string) count($items));
                if ($fig->getAttribute('data-gallery') === 'slider') { // лента прокручивается отдельно от общей подписи
                    $track = $fig->ownerDocument->createElement('div');
                    $track->setAttribute('class', 'gallery-track');
                    foreach ($items as $item) {
                        $track->appendChild($item);
                    }
                    $fig->insertBefore($track, $fig->firstChild);
                }
            }
        }
    }

    /** HTML → HTML (для витрины). Без figure — как есть. */
    public static function normalizeHtml(string $html, bool $render = true): string
    {
        if (! str_contains($html, '<figure')) {
            return $html;
        }
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><body><div id="__g">'.$html.'</div></body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('__g');
        if (! $root) {
            return $html;
        }
        self::normalize($root, $render);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    /** figure, в которой картинка (возможно, в ссылке) и необязательная подпись. */
    private static function isImageFigure(\DOMElement $fig): bool
    {
        $hasImg = false;
        foreach ($fig->childNodes as $c) {
            if ($c instanceof \DOMText && trim($c->textContent) === '') {
                continue;
            }
            if (! $c instanceof \DOMElement) {
                return false;
            }
            $tag = strtolower($c->tagName);
            if ($tag === 'img' || ($tag === 'a' && $c->getElementsByTagName('img')->length === 1 && trim($c->textContent) === '')) {
                if ($hasImg) {
                    return false;
                }
                $hasImg = true;
            } elseif ($tag !== 'figcaption') {
                return false;
            }
        }

        return $hasImg;
    }

    /** ul > li > figure (старый WordPress) → список figure, или null, если в списке что-то другое. */
    private static function listFigures(\DOMElement $list): ?array
    {
        $out = [];
        foreach ($list->childNodes as $li) {
            if ($li instanceof \DOMText && trim($li->textContent) === '') {
                continue;
            }
            if (! $li instanceof \DOMElement || strtolower($li->tagName) !== 'li') {
                return null;
            }
            $figs = [];
            foreach ($li->childNodes as $c) {
                if ($c instanceof \DOMText && trim($c->textContent) === '') {
                    continue;
                }
                if (! $c instanceof \DOMElement || strtolower($c->tagName) !== 'figure' || ! self::isImageFigure($c)) {
                    return null;
                }
                $figs[] = $c;
            }
            array_push($out, ...$figs);
        }

        return $out ?: null;
    }
}
