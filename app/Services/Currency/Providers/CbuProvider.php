<?php

namespace App\Services\Currency\Providers;

use App\Services\Currency\RateProvider;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A40 · Центральный банк Узбекистана: JSON https://cbu.uz/ru/arkhiv-kursov-valyut/json/all/2026-11-27/
 * [{"Ccy":"USD","CcyNm_RU":"Доллар США","Nominal":"1","Rate":"12050.12","Diff":"-3.10","Date":"27.11.2026"}, …]
 * Подготовлен для страны UZ; живой ответ проверить при добавлении страны.
 */
class CbuProvider implements RateProvider
{
    public function key(): string { return 'cbu'; }

    public function short(): string { return 'ЦБ РУз'; }

    public function name(): string { return 'Центральный банк Республики Узбекистан'; }

    public function url(): string { return 'https://cbu.uz/ru/arkhiv-kursov-valyut/'; }

    public function fetch(CarbonInterface $date): array
    {
        $res = Http::timeout(8)->retry(2, 400, throw: false)->acceptJson()
            ->get('https://cbu.uz/ru/arkhiv-kursov-valyut/json/all/'.$date->format('Y-m-d').'/');
        if (! $res->successful() || ! is_array($res->json())) {
            throw new RuntimeException('ЦБ РУз ответил HTTP '.$res->status());
        }

        return self::parse($res->json());
    }

    public static function parse(array $items): array
    {
        $rates = [];
        $day = null;
        foreach ($items as $i) {
            $code = strtoupper((string) ($i['Ccy'] ?? ''));
            $rate = (float) ($i['Rate'] ?? 0);
            if (! preg_match('/^[A-Z]{3}$/', $code) || $rate <= 0) {
                continue;
            }
            $day ??= $i['Date'] ?? null;
            $rates[] = ['currency' => $code, 'name' => $i['CcyNm_RU'] ?? null, 'nominal' => max(1, (int) ($i['Nominal'] ?? 1)),
                'rate' => $rate, 'change' => isset($i['Diff']) ? (float) $i['Diff'] : null];
        }
        if (! $rates || ! $day) {
            throw new RuntimeException('ЦБ РУз: в ответе нет курсов');
        }

        return ['date' => Carbon::createFromFormat('d.m.Y', $day)->toDateString(), 'rates' => $rates];
    }
}
