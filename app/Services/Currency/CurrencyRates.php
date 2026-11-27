<?php

namespace App\Services\Currency;

use App\Models\Country;
use App\Models\CurrencyRate;
use App\Support\CurrencyInfo;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * A40 · Официальные курсы валют по странам.
 * - провайдер страны — config('banq.fx.providers')[код страны] (KZ → НБРК);
 * - sync() забирает курсы на дату и складывает в currency_rates (одна строка на валюту и дату);
 * - board() — витрина: выбранные в админке валюты, изменение к прошлому курсу, история для спарклайна;
 * - ensureFresh() — если курса на сегодня нет, обновляет после ответа (defer), не чаще раза в fx.retry_minutes:
 *   работает и без cron; при недоступном источнике показываем последний сохранённый курс с его датой.
 * Статус последней попытки — в кеше `banq.fx.status.{KZ}`.
 */
class CurrencyRates
{
    /** @var array<string, ?array> */
    private array $boards = [];

    public function provider(?Country $country): ?RateProvider
    {
        $class = $country ? (config('banq.fx.providers')[$country->code] ?? null) : null;

        return $class ? app($class) : null;
    }

    /** Коды валют, выбранные в админке (или по умолчанию для страны). */
    public function codes(Country $country): array
    {
        $chosen = (array) (settings('fx.currencies')[$country->code] ?? []);

        return $chosen ?: (config('banq.fx.defaults')[$country->code] ?? ['USD', 'EUR']);
    }

    public function today(Country $country): Carbon
    {
        return Carbon::now($country->timezone ?: config('app.timezone'))->startOfDay();
    }

    /**
     * Забрать курсы на дату и сохранить. Возвращает число сохранённых курсов.
     *
     * @throws Throwable источник недоступен — статус записан, данные в базе не тронуты
     */
    public function sync(Country $country, ?CarbonInterface $date = null): int
    {
        $provider = $this->provider($country) ?? throw new \RuntimeException('Для страны '.$country->code.' нет источника курсов');
        $date ??= $this->today($country);

        try {
            $data = $provider->fetch($date);
        } catch (Throwable $e) {
            $this->status($country, ['ok' => false, 'error' => $e->getMessage()]);
            Log::warning('A40 · курсы валют: '.$country->code.' — '.$e->getMessage());
            throw $e;
        }

        $now = now();
        $rows = array_map(fn ($r) => [
            'country_id' => $country->id, 'currency' => $r['currency'], 'name' => $r['name'], 'nominal' => $r['nominal'],
            'rate' => $r['rate'], 'change' => $r['change'], 'date' => $data['date'], 'source' => $provider->key(),
            'created_at' => $now, 'updated_at' => $now,
        ], $data['rates']);
        DB::transaction(fn () => CurrencyRate::query()->upsert($rows, ['country_id', 'currency', 'date'], ['name', 'nominal', 'rate', 'change', 'source', 'updated_at']));

        $this->status($country, ['ok' => true, 'date' => $data['date'], 'count' => count($rows)]);
        unset($this->boards[$country->code]);

        return count($rows);
    }

    /** Догрузить историю за последние $days дней (для спарклайна). Возвращает число дат, которые удалось получить. */
    public function backfill(Country $country, int $days): int
    {
        $ok = 0;
        $have = CurrencyRate::query()->where('country_id', $country->id)->distinct()->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->flip();
        for ($i = $days; $i >= 0; $i--) {
            $d = $this->today($country)->subDays($i);
            if ($i > 0 && $have->has($d->toDateString())) {
                continue;
            }
            try {
                $this->sync($country, $d);
                $ok++;
            } catch (Throwable) {
                // пропуск дня — источник недоступен; продолжаем
            }
        }

        return $ok;
    }

    /** Если курса на сегодня нет — обновить после ответа посетителю (не чаще раза в N минут). */
    public function ensureFresh(?Country $country): void
    {
        if (! $country || ! config('banq.fx.auto_refresh') || ! $this->provider($country)) {
            return;
        }
        $latest = $this->latestDate($country);
        if ($latest && $latest->gte($this->today($country))) {
            return;
        }
        if (! Cache::add('banq.fx.attempt.'.$country->code, true, now()->addMinutes((int) config('banq.fx.retry_minutes', 30)))) {
            return;
        }
        defer(function () use ($country) {
            try {
                $this->sync($country);
            } catch (Throwable) {
                // статус уже записан; витрина покажет прошлый курс
            }
        });
    }

    public function latestDate(Country $country): ?Carbon
    {
        $d = CurrencyRate::query()->where('country_id', $country->id)->max('date');

        return $d ? Carbon::parse($d) : null;
    }

    /**
     * Витрина курсов страны: null — у страны нет источника.
     *
     * @return array{provider: RateProvider, date: ?Carbon, stale: bool, base: string, symbol: string,
     *               items: list<array{code: string, name: string, flag: string, symbol: string, nominal: int, rate: float, unit: float, delta: ?float, pct: ?float, trend: list<float>}>}|null
     */
    public function board(?Country $country, ?array $codes = null): ?array
    {
        if (! $country || ! ($provider = $this->provider($country))) {
            return null;
        }
        $key = $country->code.':'.implode(',', $codes ?? []);
        if (array_key_exists($key, $this->boards)) {
            return $this->boards[$key];
        }

        $codes ??= $this->codes($country);
        $date = $this->latestDate($country);
        $items = [];
        if ($date) {
            $since = $date->copy()->subDays((int) config('banq.fx.trend_days', 30));
            $history = CurrencyRate::query()->where('country_id', $country->id)->whereIn('currency', $codes)
                ->whereBetween('date', [$since->toDateString(), $date->toDateString()])->orderBy('date')->get()->groupBy('currency');

            foreach ($codes as $code) {
                $rows = $history->get($code);
                $last = $rows?->last();
                if (! $last || ! $last->date->isSameDay($date)) {
                    continue;
                }
                $prev = $rows->count() > 1 ? $rows[$rows->count() - 2] : null;
                // изменение — по данным источника (официальное к прошлому курсу), иначе — к прошлой дате в базе
                $delta = $last->change ?? ($prev ? $last->rate - $prev->rate * $last->nominal / max(1, $prev->nominal) : null);
                $base = $delta !== null ? $last->rate - $delta : null;
                $items[] = [
                    'code' => $code, 'name' => CurrencyInfo::name($code, $last->name), 'flag' => CurrencyInfo::flag($code), 'symbol' => CurrencyInfo::symbol($code),
                    'nominal' => $last->nominal, 'rate' => $last->rate, 'unit' => $last->perUnit(),
                    'delta' => $delta, 'pct' => $base ? $delta / $base * 100 : null,
                    'trend' => $rows->map(fn (CurrencyRate $r) => round($r->perUnit(), 6))->values()->all(),
                ];
            }
        }

        return $this->boards[$key] = [
            'provider' => $provider, 'date' => $date, 'stale' => ! $date || $date->lt($this->today($country)->subDays(3)),
            'base' => $country->currency, 'symbol' => $country->symbol(), 'items' => $items,
        ];
    }

    /** Все валюты, которые источник отдал на последнюю дату (для выбора в админке). */
    public function available(Country $country): array
    {
        $date = $this->latestDate($country);
        if (! $date) {
            return [];
        }

        return CurrencyRate::query()->where('country_id', $country->id)->whereDate('date', $date)->orderBy('currency')->get()
            ->mapWithKeys(fn (CurrencyRate $r) => [$r->currency => CurrencyInfo::name($r->currency, $r->name)])->all();
    }

    public function lastStatus(Country $country): ?array
    {
        return Cache::get('banq.fx.status.'.$country->code);
    }

    private function status(Country $country, array $status): void
    {
        Cache::forever('banq.fx.status.'.$country->code, $status + ['at' => now()->toIso8601String()]);
    }
}
