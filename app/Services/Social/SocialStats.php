<?php

namespace App\Services\Social;

use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * A46 · Соцсети TSOY.IN и счётчики подписчиков.
 *
 * Список — настройка social.networks: [{network, url, label?, followers? (ручное число), visible}].
 * Счётчики, полученные по API, — настройка social.counts: {network: {count, at, source, error?}}; хранится в базе,
 * поэтому очистка кеша их не теряет. На витрине число = свежий счётчик API, иначе ручное число из админки.
 *
 * Открытые способы: YouTube Data API (ключ), Telegram — публичная страница t.me (у каналов «N subscribers»)
 * или Bot API (токен), VK — API (сервисный токен). X, Instagram, Facebook бесплатного открытого API не дают — ручное число.
 * Ключи — settings social.keys.{youtube,telegram,vk}, иначе .env (config banq.social.keys).
 */
class SocialStats
{
    /** Известные сети: подпись, цвет, умеем ли считать автоматически и что для этого нужно. */
    public const NETWORKS = [
        'youtube' => ['label' => 'YouTube', 'color' => '#ff0033', 'noun' => ['подписчик', 'подписчика', 'подписчиков'], 'auto' => 'ключ YouTube Data API'],
        'telegram' => ['label' => 'Telegram', 'color' => '#27a7e7', 'noun' => ['подписчик', 'подписчика', 'подписчиков'], 'auto' => 'страница t.me (канал) или токен бота'],
        'x' => ['label' => 'X', 'color' => '#111111', 'noun' => ['читатель', 'читателя', 'читателей'], 'auto' => null],
        'vk' => ['label' => 'ВКонтакте', 'color' => '#0077ff', 'noun' => ['подписчик', 'подписчика', 'подписчиков'], 'auto' => 'сервисный токен VK'],
        'instagram' => ['label' => 'Instagram', 'color' => '#e1306c', 'noun' => ['подписчик', 'подписчика', 'подписчиков'], 'auto' => null],
        'facebook' => ['label' => 'Facebook', 'color' => '#1877f2', 'noun' => ['подписчик', 'подписчика', 'подписчиков'], 'auto' => null],
        'tiktok' => ['label' => 'TikTok', 'color' => '#111111', 'noun' => ['подписчик', 'подписчика', 'подписчиков'], 'auto' => null],
        'threads' => ['label' => 'Threads', 'color' => '#111111', 'noun' => ['подписчик', 'подписчика', 'подписчиков'], 'auto' => null],
        'linkedin' => ['label' => 'LinkedIn', 'color' => '#0a66c2', 'noun' => ['подписчик', 'подписчика', 'подписчиков'], 'auto' => null],
        'github' => ['label' => 'GitHub', 'color' => '#24292f', 'noun' => ['подписчик', 'подписчика', 'подписчиков'], 'auto' => 'открытый API GitHub'],
        'site' => ['label' => 'Сайт', 'color' => '#7c3aed', 'noun' => ['читатель', 'читателя', 'читателей'], 'auto' => null],
    ];

    /** Счётчики старше — обновляются при заходе посетителя (после ответа). */
    private const STALE_HOURS = 12;

    public function __construct(private readonly Settings $settings) {}

    /**
     * Сети для витрины: только видимые, с числом и его источником.
     *
     * @return list<array{network: string, label: string, url: string, handle: string, color: string, count: ?int, source: string, noun: array}>
     */
    public function profiles(bool $all = false): array
    {
        $counts = (array) $this->settings->get('social.counts', []);
        $out = [];
        foreach ((array) $this->settings->get('social.networks', []) as $row) {
            $key = (string) ($row['network'] ?? '');
            $url = trim((string) ($row['url'] ?? ''));
            if (! isset(self::NETWORKS[$key]) || $url === '' || (! $all && ! ($row['visible'] ?? true))) {
                continue;
            }
            $meta = self::NETWORKS[$key];
            $auto = $counts[$key]['count'] ?? null;
            $manual = isset($row['followers']) && $row['followers'] !== '' && $row['followers'] !== null ? (int) $row['followers'] : null;
            $out[] = [
                'network' => $key,
                'label' => trim((string) ($row['label'] ?? '')) ?: $meta['label'],
                'url' => $url,
                'handle' => self::handle($key, $url),
                'color' => $meta['color'],
                'count' => $auto ?? $manual,
                'source' => $auto !== null ? 'api' : ($manual !== null ? 'manual' : 'none'),
                'at' => $counts[$key]['at'] ?? null,
                'error' => $counts[$key]['error'] ?? null,
                'noun' => $meta['noun'],
            ];
        }

        return $out;
    }

    /** Сумма подписчиков по видимым сетям. */
    public function total(): int
    {
        return (int) collect($this->profiles())->sum('count');
    }

    /**
     * Обновить счётчики всех сетей, которые умеем считать. Ошибка одной сети не мешает другим;
     * при ошибке прошлое число остаётся, пишется только error.
     *
     * @return array<string, array{count: ?int, error: ?string}>
     */
    public function refresh(): array
    {
        $counts = (array) $this->settings->get('social.counts', []);
        $result = [];
        foreach ((array) $this->settings->get('social.networks', []) as $row) {
            $key = (string) ($row['network'] ?? '');
            $url = trim((string) ($row['url'] ?? ''));
            if (! isset(self::NETWORKS[$key]) || $url === '' || ! self::NETWORKS[$key]['auto']) {
                continue;
            }
            try {
                $n = $this->fetch($key, $url);
                if ($n === null) {
                    // Способа нет (нет ключа) — старое число API не держим, чтобы не показывать вечную цифру
                    unset($counts[$key]);
                    $result[$key] = ['count' => null, 'error' => null];

                    continue;
                }
                $counts[$key] = ['count' => $n, 'at' => now()->toIso8601String(), 'source' => 'api'];
                $result[$key] = ['count' => $n, 'error' => null];
            } catch (Throwable $e) {
                $msg = mb_substr($e->getMessage(), 0, 200);
                $counts[$key] = array_merge($counts[$key] ?? ['count' => null], ['error' => $msg, 'error_at' => now()->toIso8601String()]);
                $result[$key] = ['count' => $counts[$key]['count'] ?? null, 'error' => $msg];
            }
        }
        $this->settings->set(['social.counts' => $counts, 'social.refreshed_at' => now()->toIso8601String()], 'social');

        return $result;
    }

    /** Если счётчики устарели — обновить после ответа посетителю (не чаще раза в час на случай ошибок). */
    public function ensureFresh(): void
    {
        if (! config('banq.social.auto_refresh', true)) {
            return;
        }
        $at = $this->settings->get('social.refreshed_at');
        if ($at && Carbon::parse($at)->gt(now()->subHours(self::STALE_HOURS))) {
            return;
        }
        if (! Cache::add('banq.social.attempt', true, now()->addHour())) {
            return;
        }
        defer(function () {
            try {
                $this->refresh();
            } catch (Throwable) {
                // витрина покажет прошлые числа
            }
        });
    }

    /** Число подписчиков сети или null, если без ключа считать нечем. */
    public function fetch(string $network, string $url): ?int
    {
        return match ($network) {
            'youtube' => $this->youtube($url),
            'telegram' => $this->telegram($url),
            'vk' => $this->vk($url),
            'github' => $this->github($url),
            default => null,
        };
    }

    private function youtube(string $url): ?int
    {
        $key = $this->key('youtube');
        if (! $key) {
            return null;
        }
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $query = preg_match('~^channel/(UC[\w-]{10,})~', $path, $m) ? ['id' => $m[1]]
            : (preg_match('~^@([\w.-]+)~', $path, $m) ? ['forHandle' => '@'.$m[1]] : null);
        if (! $query) {
            throw new RuntimeException('Адрес канала не похож на youtube.com/channel/UC… или youtube.com/@имя');
        }
        $r = Http::timeout(8)->acceptJson()->get('https://www.googleapis.com/youtube/v3/channels', $query + ['part' => 'statistics', 'key' => $key]);
        if ($r->failed()) {
            throw new RuntimeException('YouTube API: '.($r->json('error.message') ?? 'HTTP '.$r->status()));
        }
        $n = $r->json('items.0.statistics.subscriberCount');
        if ($n === null) {
            throw new RuntimeException($r->json('items.0.statistics.hiddenSubscriberCount') ? 'YouTube: число подписчиков скрыто в настройках канала' : 'YouTube: канал не найден');
        }

        return (int) $n;
    }

    private function telegram(string $url): ?int
    {
        $name = self::handle('telegram', $url);
        if ($name === '') {
            throw new RuntimeException('Адрес не похож на t.me/имя');
        }
        $name = ltrim($name, '@');
        if ($token = $this->key('telegram')) {
            $r = Http::timeout(8)->acceptJson()->get("https://api.telegram.org/bot{$token}/getChatMemberCount", ['chat_id' => '@'.$name]);
            if ($r->successful() && $r->json('ok')) {
                return (int) $r->json('result');
            }
        }
        // Публичная страница канала/группы: <div class="tgme_page_extra">1 234 subscribers</div>
        $r = Http::timeout(8)->withHeaders(['Accept-Language' => 'en'])->get('https://t.me/'.rawurlencode($name));
        if ($r->failed()) {
            throw new RuntimeException('t.me: HTTP '.$r->status());
        }
        if (preg_match('~tgme_page_extra[^>]*>\s*([\d\s\x{00a0},.]+)\s*(subscribers?|members?|подписчик|участник)~iu', $r->body(), $m)) {
            return (int) preg_replace('~\D~', '', $m[1]);
        }
        throw new RuntimeException('Telegram: это личный аккаунт или закрытый чат — число подписчиков видно только у каналов и групп. Укажите число вручную.');
    }

    private function vk(string $url): ?int
    {
        $token = $this->key('vk');
        if (! $token) {
            return null;
        }
        $name = self::handle('vk', $url);
        $api = fn (string $method, array $q) => Http::timeout(8)->acceptJson()->get('https://api.vk.com/method/'.$method, $q + ['access_token' => $token, 'v' => '5.199']);

        $g = $api('groups.getById', ['group_id' => $name, 'fields' => 'members_count']);
        $n = $g->json('response.groups.0.members_count') ?? $g->json('response.0.members_count');
        if ($n !== null) {
            return (int) $n;
        }
        $u = $api('users.get', ['user_ids' => $name, 'fields' => 'followers_count']);
        $n = $u->json('response.0.followers_count');
        if ($n !== null) {
            return (int) $n;
        }
        throw new RuntimeException('VK API: '.($u->json('error.error_msg') ?? $g->json('error.error_msg') ?? 'страница не найдена'));
    }

    private function github(string $url): ?int
    {
        $name = self::handle('github', $url);
        $r = Http::timeout(8)->acceptJson()->get('https://api.github.com/users/'.rawurlencode($name));
        if ($r->failed()) {
            throw new RuntimeException('GitHub: HTTP '.$r->status());
        }

        return (int) $r->json('followers');
    }

    private function key(string $network): ?string
    {
        $stored = (array) $this->settings->get('social.keys', []);

        return ($stored[$network] ?? null) ?: config("banq.social.keys.{$network}") ?: null;
    }

    /** Имя аккаунта из адреса: t.me/tsoyin → tsoyin, youtube.com/@name → @name. */
    public static function handle(string $network, string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $first = explode('/', $path)[0] ?? '';
        if ($network === 'youtube') {
            return str_starts_with($first, '@') ? $first : ($first === 'channel' ? 'канал' : $first);
        }
        if ($network === 'site') {
            return (string) parse_url($url, PHP_URL_HOST);
        }

        return $first === '' ? '' : ($network === 'telegram' || $network === 'x' || $network === 'instagram' || $network === 'threads' || $network === 'tiktok' ? '@'.ltrim($first, '@') : $first);
    }

    /** 1 234 → «1,2 тыс.»; 3 400 000 → «3,4 млн». */
    public static function compact(?int $n): string
    {
        if ($n === null) {
            return '—';
        }
        if ($n < 10000) {
            return number_format($n, 0, ',', ' ');
        }
        [$div, $suffix] = $n >= 1_000_000 ? [1_000_000, ' млн'] : [1000, ' тыс.'];
        $v = $n / $div;

        $s = number_format($v, $v < 100 ? 1 : 0, ',', '');

        return (str_contains($s, ',') ? rtrim(rtrim($s, '0'), ',') : $s).$suffix;
    }
}
