<?php

namespace App\Support\Blog;

/**
 * A37 · Очистка HTML публикации по белому списку (редактор, импорт, API).
 * Остаётся только разметка, которую умеет редактор: абзацы, заголовки H2–H4, списки, цитаты, код,
 * ссылки (http/https/mailto/относительные), картинки с подписями, таблицы, врезки <aside data-tone>,
 * вставки продуктов <div data-embed="bank|deposit|card|package|payment_system" data-slug>, видео <div data-embed="youtube" data-slug="ID"> (A43).
 * Всё прочее (script, style, on*-атрибуты, javascript:-ссылки, iframe) выбрасывается, текст сохраняется.
 */
class HtmlSanitizer
{
    /** Тег → разрешённые атрибуты. */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [], 'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'del' => [], 'mark' => [], 'sup' => [], 'sub' => [], 'code' => ['class'],
        'a' => ['href', 'title', 'target', 'rel'], 'ul' => [], 'ol' => ['start'], 'li' => [], 'blockquote' => [], 'pre' => [],
        'figure' => [], 'figcaption' => [], 'img' => ['src', 'alt', 'width', 'height', 'title'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'], 'colgroup' => [], 'col' => [],
        'aside' => ['data-tone'], 'div' => ['data-embed', 'data-slug'], 'span' => [],
    ];

    /** Теги, которые удаляются вместе с содержимым. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'noscript', 'template', 'svg', 'math', 'head', 'title', 'meta', 'link'];

    public const TONES = ['info', 'tip', 'warn', 'money'];

    public const EMBEDS = ['bank', 'deposit', 'card', 'package', 'payment_system', 'youtube']; // A43 · youtube: data-slug = id видео

    public function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><body><div id="__root">'.$html.'</div></body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('__root');
        if (! $root) {
            return e(strip_tags($html));
        }
        $this->walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        // пустые абзацы, которые оставляет редактор в конце
        return trim(preg_replace('#(<p>(\s|&nbsp;|<br>)*</p>\s*)+$#u', '', $out));
    }

    private function walk(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $node->removeChild($child);

                continue;
            }
            if (! $child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }
            if ($tag === 'h1') { // заголовок страницы один — H1 в теле становится H2
                $child = $this->rename($child, 'h2');
                $tag = 'h2';
            }
            if (! array_key_exists($tag, self::ALLOWED)) {
                $this->walk($child);
                $this->unwrap($child);

                continue;
            }

            $this->filterAttributes($child, $tag);

            if ($tag === 'div' && ! $child->hasAttribute('data-embed')) {
                $this->walk($child);
                $this->unwrap($child);

                continue;
            }
            if ($tag === 'div') { // вставка продукта — пустой узел, содержимое рисует сервер
                while ($child->firstChild) {
                    $child->removeChild($child->firstChild);
                }

                continue;
            }
            if ($tag === 'img' && ! $child->getAttribute('src')) {
                $node->removeChild($child);

                continue;
            }
            $this->walk($child);
        }
    }

    private function filterAttributes(\DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->name);
            }
        }

        if ($tag === 'a') {
            $href = $this->safeUrl($el->getAttribute('href'), allowMail: true);
            $href === null ? $el->removeAttribute('href') : $el->setAttribute('href', $href);
            $external = $href && preg_match('#^https?://#i', $href) && ! str_starts_with($href, url('/'));
            $el->removeAttribute('target');
            if ($external) {
                $el->setAttribute('target', '_blank');
                $el->setAttribute('rel', 'noopener nofollow');
            } else {
                $el->removeAttribute('rel');
            }
        }
        if ($tag === 'img') {
            $src = $this->safeUrl($el->getAttribute('src'));
            $src === null ? $el->removeAttribute('src') : $el->setAttribute('src', $src);
            foreach (['width', 'height'] as $d) {
                if ($el->hasAttribute($d) && ! ctype_digit($el->getAttribute($d))) {
                    $el->removeAttribute($d);
                }
            }
            $el->setAttribute('loading', 'lazy');
        }
        if ($tag === 'code' && $el->hasAttribute('class') && ! preg_match('/^language-[a-z0-9+#-]{1,20}$/i', $el->getAttribute('class'))) {
            $el->removeAttribute('class');
        }
        if ($tag === 'aside' && ! in_array($el->getAttribute('data-tone'), self::TONES, true)) {
            $el->setAttribute('data-tone', 'info');
        }
        if ($tag === 'div' && $el->hasAttribute('data-embed')) {
            if (! in_array($el->getAttribute('data-embed'), self::EMBEDS, true) || ! preg_match('/^[a-z0-9_-]{1,191}$/i', $el->getAttribute('data-slug'))) {
                $el->removeAttribute('data-embed');
                $el->removeAttribute('data-slug');
            }
        }
        if (in_array($tag, ['th', 'td'], true)) {
            foreach (['colspan', 'rowspan'] as $s) {
                if ($el->hasAttribute($s) && ! (ctype_digit($el->getAttribute($s)) && (int) $el->getAttribute($s) <= 20)) {
                    $el->removeAttribute($s);
                }
            }
        }
        if ($tag === 'ol' && $el->hasAttribute('start') && ! ctype_digit($el->getAttribute('start'))) {
            $el->removeAttribute('start');
        }
        if (in_array($tag, ['h2', 'h3', 'h4'], true) && $el->hasAttribute('id') && ! preg_match('/^[a-z0-9-]{1,80}$/', $el->getAttribute('id'))) {
            $el->removeAttribute('id');
        }
    }

    /** http(s), относительный путь, якорь; mailto — только для ссылок. Иначе null. */
    private function safeUrl(string $url, bool $allowMail = false): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
        // браузер выкидывает пробелы и управляющие символы из схемы («java\tscript:») — убираем их до проверки
        $url = preg_replace('/[\x00-\x20\x7F]+/', '', $url);
        if ($url === '') {
            return null;
        }
        if (preg_match('#^(https?://|/(?!/)|\#)#i', $url) || ($allowMail && preg_match('#^mailto:[^\s<>"]+$#i', $url))) {
            return $url;
        }
        // «example.com/path» без схемы — считаем внешней ссылкой
        if (! preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) && ! str_starts_with($url, '//') && preg_match('#^[\w-]+(\.[\w-]+)+(/|$)#u', $url)) {
            return 'https://'.$url;
        }

        return null;
    }

    private function unwrap(\DOMElement $el): void
    {
        $parent = $el->parentNode;
        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }

    private function rename(\DOMElement $el, string $tag): \DOMElement
    {
        $new = $el->ownerDocument->createElement($tag);
        while ($el->firstChild) {
            $new->appendChild($el->firstChild);
        }
        $el->parentNode->replaceChild($new, $el);

        return $new;
    }
}
