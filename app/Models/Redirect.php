<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * A42 · Редирект со старого адреса (301 по умолчанию). Путь хранится нормализованным (Redirect::normalize):
 * без хоста, без завершающего «/», раскодированный (кириллица как есть), в нижнем регистре.
 * Из строки запроса сохраняются только WordPress-параметры ?p= ?page_id= ?cat= ?tag= (короткие ссылки).
 */
class Redirect extends Model
{
    public const QUERY_KEYS = ['p', 'page_id', 'cat', 'tag', 'attachment_id'];

    protected $guarded = ['id'];

    protected $casts = ['hits' => 'integer', 'status' => 'integer', 'last_hit_at' => 'datetime'];

    protected static function booted(): void
    {
        static::saving(function (Redirect $r) {
            $r->from_path = self::normalize($r->from_path);
            $r->from_hash = hash('sha256', $r->from_path);
            $r->status = in_array((int) $r->status, [301, 302, 307, 308], true) ? (int) $r->status : 301;
        });
    }

    /** «https://tsoy.in/Halyk-2025/?utm=1» → «/halyk-2025»; «/?p=12» → «/?p=12». */
    public static function normalize(string $url): string
    {
        $parts = parse_url(trim($url)) ?: [];
        $path = rawurldecode($parts['path'] ?? '/');
        $path = '/'.trim(preg_replace('#/+#', '/', $path), '/');
        $path = mb_strtolower($path);

        parse_str($parts['query'] ?? '', $q);
        $q = array_intersect_key($q, array_flip(self::QUERY_KEYS));
        ksort($q);

        return mb_substr($path.($q ? '?'.http_build_query($q) : ''), 0, 500);
    }

    /** Найти редирект для запроса и засчитать переход. */
    public static function match(Request $request): ?self
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return null;
        }
        try {
            $key = self::normalize($request->getRequestUri());
            $r = self::query()->where('from_hash', hash('sha256', $key))->first();
            if (! $r && str_contains($key, '?')) { // путь без лишних параметров
                $r = self::query()->where('from_hash', hash('sha256', strtok($key, '?')))->first();
            }
        } catch (\Throwable) {
            return null; // нет таблицы — до миграций
        }
        if ($r) {
            self::query()->whereKey($r->id)->update(['hits' => $r->hits + 1, 'last_hit_at' => now()]);
        }

        return $r;
    }

    /** Добавить или обновить (по старому пути). */
    public static function remember(string $from, string $to, ?string $source = null, ?string $note = null): self
    {
        $from = self::normalize($from);

        return self::query()->updateOrCreate(['from_hash' => hash('sha256', $from)], ['from_path' => $from, 'to_url' => $to, 'source' => $source, 'note' => $note]);
    }

    public function target(): string
    {
        return preg_match('#^https?://#i', $this->to_url) ? $this->to_url : url($this->to_url);
    }
}
