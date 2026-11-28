<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdSlot;
use App\Models\AdStat;
use App\Support\Installation;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A51 · Своя реклама: выбор объявления для места, подписанные метки показа/клика, учёт статистики.
 *
 * Правила «не бесить»: не больше ads.max_per_page мест на страницу, одна кампания — один раз на странице,
 * частота на посетителя в день (cookie banq_ads, обновляется только засчитанным показом), «Скрыть» — кампания
 * не показывается этому посетителю 30 дней (cookie banq_ads_hide). Без всплывающих окон и автозвука;
 * место резервируется по пропорциям слота — вёрстка не сдвигается.
 * Показ засчитывается, когда ≥ 50% объявления видно ≥ 1 с (resources/js/ads.js → POST /ads/e), клик — через /ads/c/{метка}.
 * Сотрудникам реклама показывается (для проверки), но не считается. Боты не считаются.
 */
class AdServer
{
    public const POOL = 'banq.ads.pool';

    public const SLOTS = 'banq.ads.slots';

    public const FREQ_COOKIE = 'banq_ads';

    public const HIDE_COOKIE = 'banq_ads_hide';

    public const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|headless|lighthouse|pingdom|curl|wget|python|http-client/i';

    private int $served = 0;

    private array $campaigns = [];

    private ?array $slots = null;

    private ?array $pool = null;

    public static function flush(): void
    {
        Cache::forget(self::POOL);
        Cache::forget(self::SLOTS);
        if (app()->resolved(self::class)) {
            app(self::class)->pool = app(self::class)->slots = null; // уже выбранное на этой странице не сбрасываем
        }
    }

    public function enabled(): bool
    {
        return (bool) settings('ads.enabled', true);
    }

    /** Активные места по ключу. */
    public function slots(): array
    {
        if ($this->slots !== null) {
            return $this->slots;
        }
        try {
            return $this->slots = Installation::isInstalled()
                ? Cache::rememberForever(self::SLOTS, fn () => AdSlot::query()->where('is_active', true)->get()->keyBy('key')->map->toArray()->all())
                : [];
        } catch (Throwable) {
            return $this->slots = [];
        }
    }

    /** Объявления действующих кампаний (кеш 2 минуты — так же быстро «доходит» исчерпанный бюджет). */
    public function pool(): array
    {
        if ($this->pool !== null) {
            return $this->pool;
        }
        try {
            return $this->pool = Installation::isInstalled() ? Cache::remember(self::POOL, 120, fn () => AdCreative::query()
                ->where('is_active', true)
                ->whereHas('campaign', fn ($q) => $q->where('is_active', true)->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', now())))
                ->with('campaign')->get()
                ->map(fn (AdCreative $c) => $c->only(['id', 'campaign_id', 'type', 'title', 'body', 'cta', 'image', 'image_mobile', 'alt', 'url', 'sponsor', 'disclosure', 'html', 'weight']) + [
                    'c' => $c->campaign->only(['priority', 'freq_cap', 'max_impressions', 'max_clicks', 'impressions', 'clicks', 'countries', 'slots', 'advertiser'])
                        + ['starts_at' => $c->campaign->starts_at?->getTimestamp(), 'ends_at' => $c->campaign->ends_at?->getTimestamp()],
                ])->all()) : [];
        } catch (Throwable) {
            return $this->pool = [];
        }
    }

    /**
     * Что показать в месте: ['slot' => [...], 'creative' => [...]|null, 'fallback' => код сети|null, 'token' => метка|null] или null — место не выводится.
     */
    public function pick(string $slotKey, ?Request $request = null): ?array
    {
        $request ??= request();
        $slot = $this->slots()[$slotKey] ?? null;
        if (! $slot || ! $this->enabled() || $this->served >= max(0, (int) settings('ads.max_per_page', 3))) {
            return null;
        }
        if (settings('ads.hide_for_staff', false) && $request->user()?->isStaff()) {
            return null;
        }

        $country = country()?->code;
        $now = now()->getTimestamp();
        $freq = $this->frequency($request);
        $hidden = $this->hidden($request);
        $formats = $slot['formats'] ?: array_keys(AdSlot::FORMATS);

        $candidates = array_values(array_filter($this->pool(), function (array $a) use ($slotKey, $country, $now, $freq, $hidden, $formats) {
            $c = $a['c'];

            return in_array($a['type'], $formats, true)
                && (empty($c['slots']) || in_array($slotKey, $c['slots'], true))
                && (empty($c['countries']) || ($country && in_array($country, $c['countries'], true)))
                && (! $c['starts_at'] || $c['starts_at'] <= $now) && (! $c['ends_at'] || $c['ends_at'] > $now)
                && (! $c['max_impressions'] || $c['impressions'] < $c['max_impressions'])
                && (! $c['max_clicks'] || $c['clicks'] < $c['max_clicks'])
                && (! $c['freq_cap'] || ($freq[$a['campaign_id']] ?? 0) < $c['freq_cap'])
                && ! in_array($a['campaign_id'], $hidden, true)
                && ! in_array($a['campaign_id'], $this->campaigns, true)
                && self::complete($a);
        }));

        if (! $candidates) {
            if (trim((string) $slot['fallback_code']) === '') {
                return null;
            }
            $this->served++;

            return ['slot' => $slot, 'creative' => null, 'fallback' => $slot['fallback_code'], 'token' => null];
        }

        $creative = $this->weighted($candidates);
        $this->served++;
        $this->campaigns[] = $creative['campaign_id'];

        return ['slot' => $slot, 'creative' => $creative, 'fallback' => null, 'token' => $this->sign($creative['id'], $creative['campaign_id'], $slotKey)];
    }

    /** Есть всё, что нужно для показа своего типа. */
    public static function complete(array $a): bool
    {
        return match ($a['type']) {
            'image' => filled($a['image']) && filled($a['url']),
            'native' => filled($a['title']) && filled($a['url']),
            'html' => filled($a['html']),
            default => false,
        };
    }

    private function weighted(array $candidates): array
    {
        $total = array_sum(array_map(fn ($a) => max(1, $a['c']['priority']) * max(1, $a['weight']), $candidates));
        $roll = random_int(1, max(1, $total));
        foreach ($candidates as $a) {
            $roll -= max(1, $a['c']['priority']) * max(1, $a['weight']);
            if ($roll <= 0) {
                return $a;
            }
        }

        return $candidates[0];
    }

    /**
     * A51 · Вставить место внутрь текста статьи: перед подзаголовком после первой трети текста,
     * иначе — между абзацами там же; короткий текст (меньше 6 абзацев) — в конец. Только на верхнем уровне (не в цитате, списке, таблице).
     */
    public function inject(string $html, string $slotKey): string
    {
        $ad = $this->pick($slotKey);
        if (! $ad) {
            return $html;
        }
        $block = view('ads.slot', ['adPick' => $ad, 'class' => 'ad-inline'])->render();
        if (substr_count($html, '</p>') < 6) {
            return $html.$block;
        }

        $min = (int) (strlen($html) / 3);
        $best = null;
        preg_match_all('#</(?:p|blockquote|ul|ol|table|figure|div)>\s*(?=<(h2|h3|p)\b)#', $html, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($m as $hit) {
            $pos = $hit[0][1] + strlen($hit[0][0]);
            if ($pos < $min || ! self::topLevel(substr($html, 0, $pos))) {
                continue;
            }
            if ($hit[1][0] !== 'p') { // подзаголовок — естественная пауза
                $best = $pos;
                break;
            }
            $best ??= $pos;
            if ($pos > $min * 1.6) {
                break; // подзаголовка рядом нет — ставим между абзацами
            }
        }

        return $best === null ? $html.$block : substr($html, 0, $best).$block.substr($html, $best);
    }

    private static function topLevel(string $before): bool
    {
        foreach (['blockquote', 'ul', 'ol', 'table', 'figure', 'details', 'div', 'aside'] as $tag) {
            if (preg_match_all('#<'.$tag.'\b#i', $before) !== preg_match_all('#</'.$tag.'>#i', $before)) {
                return false;
            }
        }

        return true;
    }

    // ── Метки показа/клика ───────────────────────────────────

    public function sign(int $creative, int $campaign, string $slot): string
    {
        $body = implode('.', [$creative, $campaign, $slot, now()->getTimestamp(), bin2hex(random_bytes(6))]);

        return $body.'.'.$this->mac($body);
    }

    /** @return array{creative: int, campaign: int, slot: string, ts: int, nonce: string}|null */
    public function verify(string $token, int $maxAge): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 6) {
            return null;
        }
        [$cr, $cmp, $slot, $ts, $nonce, $mac] = $parts;
        if (! hash_equals($this->mac(implode('.', array_slice($parts, 0, 5))), $mac) || ! ctype_digit($cr) || ! ctype_digit($cmp) || ! ctype_digit($ts)
            || now()->getTimestamp() - (int) $ts > $maxAge) {
            return null;
        }

        return ['creative' => (int) $cr, 'campaign' => (int) $cmp, 'slot' => $slot, 'ts' => (int) $ts, 'nonce' => $nonce];
    }

    private function mac(string $body): string
    {
        return substr(hash_hmac('sha256', 'ads|'.$body, (string) config('app.key')), 0, 24);
    }

    // ── Учёт ────────────────────────────────────────────────

    /** Считать ли этот запрос (не бот, не сотрудник). */
    public function countable(Request $request): bool
    {
        return ! preg_match(self::BOTS, (string) $request->userAgent()) && $request->userAgent() && ! $request->user()?->isStaff();
    }

    /** +1 показ или клик: итог кампании и объявления + строка дня. Метка засчитывается один раз. */
    public function record(array $t, string $kind): bool
    {
        $column = $kind === 'click' ? 'clicks' : 'impressions';
        if (! Cache::add("ads:{$column}:{$t['nonce']}", 1, now()->addDay())) {
            return false;
        }
        $creative = AdCreative::query()->whereKey($t['creative'])->where('campaign_id', $t['campaign'])->exists();
        if (! $creative) {
            return false;
        }

        DB::transaction(function () use ($t, $column) {
            AdCreative::query()->whereKey($t['creative'])->increment($column);
            AdCampaign::query()->whereKey($t['campaign'])->increment($column);
            $key = ['date' => now()->toDateString(), 'creative_id' => $t['creative'], 'slot' => $t['slot']];
            if (! AdStat::query()->where($key)->increment($column)) {
                try {
                    AdStat::query()->create($key + ['campaign_id' => $t['campaign'], 'impressions' => 0, 'clicks' => 0, $column => 1]);
                } catch (QueryException) {
                    AdStat::query()->where($key)->increment($column); // параллельный запрос успел создать строку
                }
            }
        });

        return true;
    }

    /** Показы кампаний посетителю сегодня: cookie «Ymd|id:n,id:n». */
    public function frequency(Request $request): array
    {
        [$day, $list] = array_pad(explode('|', (string) $request->cookie(self::FREQ_COOKIE), 2), 2, '');
        if ($day !== now()->format('Ymd')) {
            return [];
        }
        $out = [];
        foreach (array_filter(explode(',', $list)) as $pair) {
            [$id, $n] = array_pad(explode(':', $pair), 2, 0);
            $out[(int) $id] = (int) $n;
        }

        return $out;
    }

    public function frequencyCookie(array $freq): string
    {
        arsort($freq);

        return now()->format('Ymd').'|'.implode(',', array_map(fn ($id, $n) => "{$id}:{$n}", array_keys(array_slice($freq, 0, 40, true)), array_slice($freq, 0, 40, true)));
    }

    /** Скрытые посетителем кампании. */
    public function hidden(Request $request): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $request->cookie(self::HIDE_COOKIE)))));
    }
}
