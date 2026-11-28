<?php

namespace App\Services\WordPress;

use App\Support\Blog\HtmlSanitizer;
use Closure;

/**
 * A43 · HTML записи WordPress → HTML журнала Banq + находки проверки безопасности.
 *
 * Проверка (scan): скрипты, iframe с чужих сайтов, on*-обработчики, javascript:/data:-ссылки, скрытые блоки со ссылками
 * (классический SEO-спам взломанных WordPress), спам-слова в ссылках, ссылки на IP-адреса и «мусорные» домены,
 * meta refresh, base64-вставки. Каждая находка — ['level' => danger|warn|info, 'kind' => …, 'detail' => …].
 *
 * Преобразование (transform):
 *  — YouTube → живая вставка <div data-embed="youtube" data-slug="ID">; прочие iframe → ссылка «Открыть: сайт»;
 *  — скрипты, стили, формы, кнопки лайтбокса — прочь; шорткоды плагинов — прочь ([wpmfpdf id] → ссылка на PDF);
 *  — картинки и файлы из /wp-content/uploads → свои копии (через $media), исходный размер вместо «-1024x576»;
 *  — ссылки на свои записи → новые адреса (через $link); <audio> → ссылка на файл (на витрине — плеер);
 *  — H5/H6 → H4, затем общий белый список HtmlSanitizer.
 */
class WpContent
{
    /** Слова SEO-спама взломанных сайтов (в адресе или тексте ссылки). */
    public const SPAM = '/\b(casino|казино|viagra|cialis|levitra|porn|порно|xxx|1xbet|melbet|mostbet|pin-?up|vulkan|вулкан\s+(?:казино|клуб)|слоты|slots?|betting|букмекер|payday|essay\s+writing|escort|эскорт|replica|kamagra|tadalafil)\b/iu';

    /** Хосты, с которых iframe — это нормальная вставка (сообщаем как info). */
    public const KNOWN_EMBEDS = ['youtube.com', 'youtube-nocookie.com', 'youtu.be', 'vimeo.com', 'drive.google.com', 'docs.google.com', 'forms.office.com',
        'datawrapper.dwcdn.net', 'e.infogram.com', 'infogram.com', 'jaicp.com', 'api-maps.yandex.ru', 'yandex.ru', 'onedrive.live.com', 'tsoy.in'];

    public const PLUGIN_SHORTCODES = 'userpro|woocommerce_[a-z_]+|wpmfpdf|contact-form-7|powerkit_[a-z_]+|caption|gallery|embed|audio|video|playlist';

    /** @var list<array{level: string, kind: string, detail: string}> */
    public array $findings = [];

    /** @var list<string> адреса, которые не удалось перенести (внешние картинки и т.п.) */
    public array $missing = [];

    /**
     * @param  list<string>  $ownHosts  хосты старого сайта (tsoy.in, www.tsoy.in)
     * @param  Closure(string $url, ?int $attachmentId): ?string|null  $media  скачать файл, вернуть локальный путь
     * @param  Closure(string $url): ?string|null  $link  новый адрес для ссылки на старый сайт
     * @param  Closure(int $id): ?string|null  $mediaById  адрес вложения по id (для [wpmfpdf id])
     */
    public function __construct(
        private array $ownHosts,
        private ?Closure $media = null,
        private ?Closure $link = null,
        private ?Closure $mediaById = null,
        private bool $localizeExternalImages = true,
    ) {}

    // ── Проверка ───────────────────────────────────────────

    public function scan(string $html): array
    {
        $f = [];
        $add = function (string $level, string $kind, string $detail) use (&$f) {
            $f[] = ['level' => $level, 'kind' => $kind, 'detail' => mb_substr(trim(preg_replace('/\s+/u', ' ', $detail)), 0, 240)];
        };

        foreach ($this->matches('#<script\b([^>]*)>([\s\S]{0,200})#i', $html) as $m) {
            $src = preg_match('#src=["\']([^"\']+)#i', $m[1], $s) ? $s[1] : null;
            $host = $src ? $this->host($src) : null;
            $inline = trim($m[2]);
            $danger = ($src && ! $this->isKnownHost($host)) || preg_match('/eval\(|atob\(|fromCharCode|document\.write|unescape\(|\\\\x[0-9a-f]{2}/i', $inline);
            $add($danger ? 'danger' : 'warn', 'script', $src ? 'скрипт с '.$host.' ('.$src.')' : 'встроенный скрипт: '.$inline);
        }
        foreach ($this->matches('#<iframe\b[^>]*src=["\']([^"\']+)#i', $html) as $m) {
            $host = $this->host($m[1]);
            $add($this->isKnownHost($host) ? 'info' : 'danger', 'iframe', 'iframe с '.$host.' ('.$m[1].')');
        }
        foreach ($this->matches('#<(object|embed|applet|form|meta|base|link)\b[^>]{0,160}#i', $html) as $m) {
            $level = preg_match('/http-equiv\s*=\s*["\']?refresh/i', $m[0]) ? 'danger' : ($m[1] === 'form' ? 'info' : 'warn');
            $add($level, strtolower($m[1]), $m[0]);
        }
        foreach ($this->matches('#\son[a-z]{3,20}\s*=\s*["\']?[^"\'>]{0,80}#i', $html) as $m) {
            $add('danger', 'on-attr', 'обработчик события: '.$m[0]);
        }
        foreach ($this->matches('#(?:href|src|action)\s*=\s*["\']?\s*(javascript|vbscript|data):([^"\'>]{0,80})#i', $html) as $m) {
            $level = strtolower($m[1]) === 'data' && preg_match('#^image/(png|jpe?g|gif|webp);#i', $m[2]) ? 'info' : 'danger';
            $add($level, 'js-link', $m[1].':'.$m[2]);
        }
        // скрытые блоки с внешними ссылками — главный признак SEO-взлома
        foreach ($this->matches('#<(\w+)[^>]*style\s*=\s*["\'][^"\']*(display\s*:\s*none|visibility\s*:\s*hidden|font-size\s*:\s*[01]px|(?:left|top|text-indent)\s*:\s*-\d{3,})[^"\']*["\'][^>]*>([\s\S]{0,600}?)</\1>#i', $html) as $m) {
            if (preg_match_all('#href=["\'](https?://[^"\']+)#i', $m[3], $links)) {
                $ext = array_filter($links[1], fn ($u) => ! $this->isOwn($u));
                if ($ext) {
                    $add('danger', 'hidden-links', 'скрытый блок со ссылками: '.implode(', ', array_slice($ext, 0, 3)));
                }
            }
        }
        foreach ($this->matches('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>([\s\S]{0,120}?)</a>#i', $html) as $m) {
            $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5));
            if (preg_match(self::SPAM, $m[1].' '.$text, $w)) {
                $add('danger', 'spam-link', '«'.$w[0].'» в ссылке '.$m[1].($text ? ' («'.$text.'»)' : ''));
            }
            $host = $this->host($m[1]);
            if ($host && (preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $host) || preg_match('/\.(xyz|top|click|loan|win|bid|icu|cyou|rest|monster|buzz|cam)$/i', $host))) {
                $add('warn', 'odd-domain', 'ссылка на '.$host.' ('.$m[1].')');
            }
            if ($this->isOwn($m[1]) && preg_match('#\.(php\d?|phtml|phar|sh|exe|js)(\?|$)#i', parse_url($m[1], PHP_URL_PATH) ?? '') && ! preg_match('#/(index|wp-login)\.php#', $m[1])) {
                $add('danger', 'own-script', 'ссылка на исполняемый файл на своём сайте: '.$m[1]);
            }
        }
        if (preg_match('/base64,[A-Za-z0-9+\/]{400,}/', $html) && ! preg_match('#data:image/[a-z]+;base64#i', $html)) {
            $add('warn', 'base64', 'длинная base64-вставка');
        }

        return $f;
    }

    // ── Преобразование ────────────────────────────────────

    public function transform(string $html): string
    {
        $this->findings = $this->scan($html);
        $html = $this->shortcodes($html);

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><body><div id="__wp">'.$html.'</div></body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('__wp');
        if (! $root) {
            return app(HtmlSanitizer::class)->clean($html);
        }
        $xp = new \DOMXPath($doc);

        // 1. Встроенные записи WordPress (<blockquote class="wp-embedded-content"> + скрытый iframe) — оставляем только ссылку
        foreach (iterator_to_array($xp->query('.//iframe[contains(@class, "wp-embedded-content")]', $root)) as $n) {
            $n->parentNode?->removeChild($n);
        }
        // 2. iframe: YouTube → вставка, остальное → ссылка
        foreach (iterator_to_array($xp->query('.//iframe', $root)) as $n) {
            $src = html_entity_decode((string) $n->getAttribute('src'), ENT_QUOTES | ENT_HTML5);
            $target = $this->outerBlock($n, 'wp-block-embed');
            if ($id = self::youtubeId($src)) {
                $this->replace($target, $this->el($doc, 'div', ['data-embed' => 'youtube', 'data-slug' => $id]));

                continue;
            }
            $host = $this->host($src);
            if ($host && preg_match('#^https?://#i', $src)) {
                $p = $this->el($doc, 'p');
                $a = $this->el($doc, 'a', ['href' => preg_replace('#/preview$#', '/view', $src)], 'Открыть: '.(str_contains($host, 'google') ? 'Google Диск' : $host));
                $p->appendChild($a);
                $this->replace($target, $p);
            } else {
                $n->parentNode?->removeChild($n);
            }
        }
        // 3. Обёртки YouTube без iframe (сырой экспорт: внутри только адрес ролика)
        foreach (iterator_to_array($xp->query('.//*[contains(@class, "wp-block-embed__wrapper")]', $root)) as $n) {
            if ($id = self::youtubeId(trim($n->textContent))) {
                $this->replace($this->outerBlock($n, 'wp-block-embed'), $this->el($doc, 'div', ['data-embed' => 'youtube', 'data-slug' => $id]));
            }
        }
        // 4. <audio> → ссылка на свой файл (витрина рисует плеер)
        foreach (iterator_to_array($xp->query('.//audio', $root)) as $n) {
            $src = $n->getAttribute('src') ?: ($xp->query('.//source', $n)->item(0)?->getAttribute('src') ?? '');
            $local = $src ? $this->media($src, null) : null;
            $p = $this->el($doc, 'p');
            $p->appendChild($this->el($doc, 'a', ['href' => $local ?? $src], '▶ '.rawurldecode(pathinfo(parse_url($src, PHP_URL_PATH) ?? 'аудио', PATHINFO_FILENAME))));
            $this->replace($this->outerBlock($n, 'wp-block-audio'), $p);
        }
        // 5. Картинки
        foreach (iterator_to_array($xp->query('.//img', $root)) as $img) {
            $src = html_entity_decode(trim((string) ($img->getAttribute('data-orig-file') ?: $img->getAttribute('src'))), ENT_QUOTES | ENT_HTML5);
            if ($src === '' || str_starts_with($src, 'data:')) {
                $img->parentNode?->removeChild($img);

                continue;
            }
            $attachment = (int) ($img->getAttribute('data-id') ?: (preg_match('/wp-image-(\d+)/', $img->getAttribute('class'), $c) ? $c[1] : 0)) ?: null;
            $isOwn = $this->isOwn($src);
            $local = ($isOwn || $this->localizeExternalImages) ? $this->media($src, $isOwn ? $attachment : null) : null;
            if ($local) {
                $img->setAttribute('src', $local);
                // размеры у уменьшенной копии WordPress не совпадают с оригиналом — пусть браузер возьмёт пропорции файла
                $img->removeAttribute('width');
                $img->removeAttribute('height');
            } elseif ($isOwn) {
                $this->missing[] = $src;
                $img->parentNode?->removeChild($img);

                continue;
            } else {
                $this->missing[] = $src;
            }
            foreach (['srcset', 'sizes', 'class', 'style', 'data-id'] as $a) {
                $img->removeAttribute($a);
            }
            // ссылка-лайтбокс вокруг картинки на эту же картинку — не нужна
            $a = $img->parentNode;
            if ($a instanceof \DOMElement && strtolower($a->tagName) === 'a' && trim($a->textContent) === '' && $this->isUpload($a->getAttribute('href'))) {
                $a->parentNode->replaceChild($img, $a);
            }
        }
        // 6. Ссылки
        foreach (iterator_to_array($xp->query('.//a[@href]', $root)) as $a) {
            $href = html_entity_decode(trim($a->getAttribute('href')), ENT_QUOTES | ENT_HTML5);
            if (! $this->isOwn($href)) {
                continue;
            }
            if ($this->isUpload($href)) {
                $local = $this->media($href, null);
                $local ? $a->setAttribute('href', $local) : $this->missing[] = $href;

                continue;
            }
            $new = $this->link ? ($this->link)($href) : null;
            $a->setAttribute('href', $new ?? $this->relative($href));
        }
        // 7. H5/H6 → H4 (у журнала заголовки H2–H4)
        foreach (['h5', 'h6'] as $tag) {
            foreach (iterator_to_array($root->getElementsByTagName($tag)) as $h) {
                $new = $doc->createElement('h4');
                while ($h->firstChild) {
                    $new->appendChild($h->firstChild);
                }
                $h->parentNode->replaceChild($new, $h);
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        $out = app(HtmlSanitizer::class)->clean($out);
        // пустые абзацы и подписи, которые оставляет WordPress
        $out = preg_replace('#<(p|figcaption)>(\s|&nbsp;|\x{00A0}|<br>)*</\1>#u', '', $out);

        return trim($out);
    }

    /** Шорткоды плагинов: [wpmfpdf id="1700"] → ссылка на PDF, остальные — прочь. */
    private function shortcodes(string $html): string
    {
        $html = preg_replace_callback('/\[wpmfpdf\s+id=(?:&#8221;|&#187;|&#8243;|["\'»″”])*(\d+)[^\]]*\]/u', function ($m) {
            $url = $this->mediaById ? ($this->mediaById)((int) $m[1]) : null;
            if (! $url) {
                return '';
            }
            $local = $this->media($url, (int) $m[1]);

            return '<a href="'.e($local ?? $url).'">📄 '.e(rawurldecode(pathinfo(parse_url($url, PHP_URL_PATH) ?? 'документ', PATHINFO_BASENAME))).'</a>';
        }, $html);

        return preg_replace('/\[\/?(?:'.self::PLUGIN_SHORTCODES.')(?:\s[^\]]*)?\]/iu', '', $html);
    }

    public static function youtubeId(string $url): ?string
    {
        return preg_match('#(?:youtube(?:-nocookie)?\.com/(?:embed/|watch\?(?:.*&)?v=|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,20})#', $url, $m) ? $m[1] : null;
    }

    private function media(string $url, ?int $attachment): ?string
    {
        if (! $this->media) {
            return null;
        }
        $url = str_starts_with($url, '//') ? 'https:'.$url : $url;
        if (str_starts_with($url, '/')) {
            $url = 'https://'.$this->ownHosts[0].$url;
        }

        return ($this->media)($url, $attachment);
    }

    public function isOwn(string $url): bool
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }
        $host = $this->host($url);

        return $host !== null && in_array(preg_replace('/^www\./', '', $host), array_map(fn ($h) => preg_replace('/^www\./', '', $h), $this->ownHosts), true);
    }

    private function isUpload(string $url): bool
    {
        return (bool) preg_match('#/wp-content/uploads/#', $url);
    }

    private function isKnownHost(?string $host): bool
    {
        if (! $host) {
            return true;
        }
        foreach ([...self::KNOWN_EMBEDS, ...$this->ownHosts] as $k) {
            if ($host === $k || str_ends_with($host, '.'.$k)) {
                return true;
            }
        }

        return false;
    }

    private function host(string $url): ?string
    {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
        $h = parse_url(str_starts_with($url, '//') ? 'https:'.$url : $url, PHP_URL_HOST);

        return $h ? strtolower($h) : null;
    }

    private function relative(string $url): string
    {
        $p = parse_url($url);

        return ($p['path'] ?? '/').(isset($p['query']) ? '?'.$p['query'] : '').(isset($p['fragment']) ? '#'.$p['fragment'] : '');
    }

    private function matches(string $re, string $s): array
    {
        preg_match_all($re, $s, $m, PREG_SET_ORDER);

        return $m;
    }

    /** Ближайший предок-блок WordPress с классом (figure.wp-block-embed), иначе сам узел. */
    private function outerBlock(\DOMNode $n, string $class): \DOMNode
    {
        for ($p = $n->parentNode; $p instanceof \DOMElement && $p->getAttribute('id') !== '__wp'; $p = $p->parentNode) {
            if (str_contains(' '.$p->getAttribute('class').' ', ' '.$class.' ')) {
                return $p;
            }
        }

        return $n;
    }

    private function replace(\DOMNode $old, \DOMNode $new): void
    {
        $old->parentNode?->replaceChild($new, $old);
    }

    private function el(\DOMDocument $doc, string $tag, array $attrs = [], ?string $text = null): \DOMElement
    {
        $el = $doc->createElement($tag);
        foreach ($attrs as $k => $v) {
            $el->setAttribute($k, $v);
        }
        if ($text !== null) {
            $el->appendChild($doc->createTextNode($text));
        }

        return $el;
    }
}
