<?php

namespace App\Services\WordPress;

use RuntimeException;

/**
 * A43 · Файл экспорта WordPress (WXR: «Инструменты → Экспорт → Всё содержимое») → снимок в формате REST API (WpSource).
 * Запасной путь, если REST API на старом сайте закрыт. Текст в WXR «сырой»: без автоабзацев и oEmbed —
 * абзацы расставляются по пустым строкам, адрес YouTube отдельной строкой становится вставкой видео.
 */
class WxrReader
{
    public function read(string $xml): array
    {
        if (preg_match('/<!DOCTYPE/i', substr($xml, 0, 2048))) {
            throw new RuntimeException('В файле есть DOCTYPE — такой XML не принимаем (защита от XXE).');
        }
        $prev = libxml_use_internal_errors(true);
        $x = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (! $x || ! isset($x->channel)) {
            throw new RuntimeException('Это не файл экспорта WordPress (WXR).');
        }
        $ns = $x->getDocNamespaces(true);
        $wpNs = $ns['wp'] ?? 'http://wordpress.org/export/1.2/';
        $ch = $x->channel;
        $wpc = $ch->children($wpNs);
        $site = rtrim((string) ($wpc->base_blog_url ?: $ch->link), '/');

        $cats = [];
        $bySlug = [];
        foreach ($wpc->category as $c) {
            $slug = (string) $c->category_nicename;
            $cats[] = ['id' => (int) $c->term_id, 'name' => (string) $c->cat_name, 'slug' => $slug, 'parent_slug' => (string) $c->category_parent, 'link' => $site.'/category/'.$slug.'/', 'count' => 0];
            $bySlug[$slug] = (int) $c->term_id;
        }
        foreach ($cats as &$c) {
            $c['parent'] = $c['parent_slug'] !== '' ? ($bySlug[$c['parent_slug']] ?? 0) : 0;
            unset($c['parent_slug']);
        }
        unset($c);
        $tags = [];
        $tagBySlug = [];
        foreach ($wpc->tag as $t) {
            $tags[] = ['id' => (int) $t->term_id, 'name' => (string) $t->tag_name, 'slug' => (string) $t->tag_slug, 'link' => $site.'/tag/'.$t->tag_slug.'/'];
            $tagBySlug[(string) $t->tag_slug] = (int) $t->term_id;
        }
        $users = [];
        $userByLogin = [];
        foreach ($wpc->author as $a) {
            $users[] = ['id' => (int) $a->author_id, 'name' => (string) ($a->author_display_name ?: $a->author_login), 'slug' => (string) $a->author_login, 'link' => $site.'/author/'.$a->author_login.'/'];
            $userByLogin[(string) $a->author_login] = (int) $a->author_id;
        }

        $out = ['posts' => [], 'pages' => [], 'media' => []];
        $comments = 0;
        foreach ($ch->item as $item) {
            $w = $item->children($wpNs);
            $type = (string) $w->post_type;
            $meta = [];
            foreach ($w->postmeta as $pm) {
                $meta[(string) $pm->meta_key] = (string) $pm->meta_value;
            }
            $dc = $item->children('http://purl.org/dc/elements/1.1/');
            $date = (string) $w->post_date_gmt;
            $base = [
                'id' => (int) $w->post_id, 'slug' => (string) $w->post_name, 'status' => (string) $w->status === 'publish' ? 'publish' : (string) $w->status,
                'link' => (string) $item->link, 'date' => str_replace(' ', 'T', (string) $w->post_date), 'date_gmt' => $date && $date !== '0000-00-00 00:00:00' ? str_replace(' ', 'T', $date) : null,
                'title' => ['rendered' => htmlspecialchars((string) $item->title, ENT_NOQUOTES)], 'author' => $userByLogin[(string) $dc->creator] ?? null,
            ];
            if ($type === 'attachment') {
                $out['media'][] = $base + ['source_url' => (string) $w->attachment_url, 'mime_type' => '', 'alt_text' => $meta['_wp_attachment_image_alt'] ?? '', 'post' => (int) $w->post_parent];

                continue;
            }
            if (! in_array($type, ['post', 'page'], true)) {
                continue;
            }
            foreach ($w->comment as $cm) {
                $comments += (string) $cm->children($wpNs)->comment_approved === '1' ? 1 : 0;
            }
            $catIds = [];
            $tagIds = [];
            foreach ($item->category as $c) {
                $nice = (string) $c['nicename'];
                (string) $c['domain'] === 'category' ? ($catIds[] = $bySlug[$nice] ?? null) : ((string) $c['domain'] === 'post_tag' ? ($tagIds[] = $tagBySlug[$nice] ?? null) : null);
            }
            $content = (string) $item->children('http://purl.org/rss/1.0/modules/content/')->encoded;
            $excerpt = (string) $item->children('http://wordpress.org/export/1.2/excerpt/')->encoded;
            $out[$type === 'post' ? 'posts' : 'pages'][] = $base + [
                'type' => $type, 'parent' => (int) $w->post_parent, 'content' => ['rendered' => self::autop($content)], 'excerpt' => ['rendered' => $excerpt !== '' ? '<p>'.htmlspecialchars($excerpt, ENT_NOQUOTES).'</p>' : ''],
                'categories' => array_values(array_filter($catIds)), 'tags' => array_values(array_filter($tagIds)),
                'featured_media' => (int) ($meta['_thumbnail_id'] ?? 0), 'comment_status' => (string) $w->comment_status, 'sticky' => (int) $w->is_sticky === 1, 'modified_gmt' => null,
            ];
        }
        foreach ($cats as &$c) {
            $c['count'] = collect($out['posts'])->filter(fn ($p) => in_array($c['id'], $p['categories'], true))->count();
        }
        unset($c);

        return ['site' => ['name' => (string) $ch->title, 'description' => (string) $ch->description, 'url' => $site, 'home' => $site],
            'fetched_at' => now()->toIso8601String(), 'source' => 'wxr', 'categories' => $cats, 'tags' => $tags, 'users' => $users,
            'comments_total' => $comments] + $out;
    }

    /** Упрощённый wpautop: блоки Gutenberg уже размечены, классический текст — абзацы по пустым строкам. */
    public static function autop(string $html): string
    {
        $html = preg_replace('/<!--\s*\/?wp:[^>]*-->/u', '', $html);
        // адрес YouTube отдельной строкой — oEmbed
        $html = preg_replace('#^[ \t]*(https?://(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/)[A-Za-z0-9_-]{6,20}\S*)[ \t]*$#mu', '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">$1</div></figure>', $html);
        if (preg_match('#<(p|h[1-6]|ul|ol|figure|table|blockquote)\b#i', $html) && ! preg_match('/\n\s*\n/', strip_tags($html))) {
            return trim($html);
        }
        $blocks = preg_split('/\n\s*\n/u', str_replace(["\r\n", "\r"], "\n", trim($html)));

        return implode("\n", array_map(function ($b) {
            $b = trim($b);

            return $b === '' || preg_match('#^<(p|h[1-6]|ul|ol|figure|table|blockquote|div|pre|hr)\b#i', $b) ? $b : '<p>'.nl2br($b, false).'</p>';
        }, $blocks));
    }
}
