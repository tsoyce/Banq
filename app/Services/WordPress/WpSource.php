<?php

namespace App\Services\WordPress;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A43 · Чтение сайта WordPress через REST API (/wp-json/wp/v2): посты, страницы, медиа, рубрики, теги, авторы.
 * Снимок — обычный массив (тот же формат, что отдаёт API), его можно сохранить в JSON и импортировать без сети.
 * Запасной путь без API — файл экспорта WordPress (WXR, «Инструменты → Экспорт»): WxrReader.
 */
class WpSource
{
    public const TYPES = ['posts', 'pages', 'media', 'categories', 'tags', 'users'];

    public function __construct(private int $timeout = 30) {}

    /** @return array{site: array, fetched_at: string, posts: list<array>, pages: list<array>, media: list<array>, categories: list<array>, tags: list<array>, users: list<array>, comments_total: int} */
    public function snapshot(string $baseUrl): array
    {
        $base = rtrim($baseUrl, '/');
        $site = $this->get($base.'/wp-json/');
        if (! is_array($site->json()) || ! isset($site->json()['namespaces'])) {
            throw new RuntimeException('По адресу '.$base.'/wp-json/ нет REST API WordPress. Загрузите файл экспорта (WXR).');
        }
        $info = $site->json();

        $out = [
            'site' => ['name' => $info['name'] ?? '', 'description' => $info['description'] ?? '', 'url' => rtrim($info['url'] ?? $base, '/'), 'home' => rtrim($info['home'] ?? $base, '/'), 'gmt_offset' => $info['gmt_offset'] ?? 0],
            'fetched_at' => now()->toIso8601String(),
            'source' => 'rest',
        ];
        foreach (self::TYPES as $type) {
            // посты и страницы — только опубликованные (черновики без входа API не отдаёт); медиа — со статусом inherit
            $query = in_array($type, ['posts', 'pages'], true) ? ['status' => 'publish'] : [];
            $out[$type] = $this->all($base, $type, $query);
        }
        $c = $this->get($base.'/wp-json/wp/v2/comments', ['per_page' => 1], allowFail: true);
        $out['comments_total'] = (int) ($c?->header('X-WP-Total') ?: 0);

        return $out;
    }

    /** Все страницы выдачи одного типа (по 100). */
    public function all(string $base, string $type, array $query = []): array
    {
        $items = [];
        for ($page = 1; $page <= 200; $page++) {
            $r = $this->get($base.'/wp-json/wp/v2/'.$type, $query + ['per_page' => 100, 'page' => $page, 'orderby' => 'id', 'order' => 'asc'], allowFail: $page > 1 || $type === 'users');
            if (! $r || ! is_array($r->json())) {
                break;
            }
            $items = array_merge($items, $r->json());
            if (count($r->json()) < 100 || $page >= (int) ($r->header('X-WP-TotalPages') ?: 1)) {
                break;
            }
        }

        return $items;
    }

    private function get(string $url, array $query = [], bool $allowFail = false): ?\Illuminate\Http\Client\Response
    {
        try {
            $r = Http::timeout($this->timeout)->retry(2, 500, throw: false)->acceptJson()
                ->withUserAgent('BanqImporter/1.0 (+WordPress migration)')->get($url, $query);
        } catch (\Throwable $e) {
            if ($allowFail) {
                return null;
            }
            throw new RuntimeException('Не удалось открыть '.$url.': '.$e->getMessage());
        }
        if ($r->failed()) {
            if ($allowFail) {
                return null;
            }
            throw new RuntimeException('WordPress ответил '.$r->status().' на '.$url);
        }

        return $r;
    }
}
