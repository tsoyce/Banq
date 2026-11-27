<?php

namespace App\Services\Currency\Providers;

use App\Services\Currency\RateProvider;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A40 · Банк России: XML https://www.cbr.ru/scripts/XML_daily.asp?date_req=27/11/2026
 * <ValCurs Date="27.11.2026"><Valute><CharCode>USD</CharCode><Nominal>1</Nominal><Name>…</Name><Value>80,1234</Value>…
 * Изменения в ответе нет — считается по прошлому дню в базе. Подготовлен для страны RU.
 */
class CbrProvider implements RateProvider
{
    public function key(): string { return 'cbr'; }

    public function short(): string { return 'ЦБ РФ'; }

    public function name(): string { return 'Центральный банк Российской Федерации'; }

    public function url(): string { return 'https://www.cbr.ru/currency_base/daily/'; }

    public function fetch(CarbonInterface $date): array
    {
        $res = Http::timeout(8)->retry(2, 400, throw: false)->get('https://www.cbr.ru/scripts/XML_daily.asp', ['date_req' => $date->format('d/m/Y')]);
        if (! $res->successful()) {
            throw new RuntimeException('ЦБ РФ ответил HTTP '.$res->status());
        }

        return self::parse($res->body());
    }

    public static function parse(string $xml): array
    {
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($prev);
        if ($doc === false || ! isset($doc->Valute)) {
            throw new RuntimeException('ЦБ РФ: ответ не похож на курсы');
        }
        $rates = [];
        foreach ($doc->Valute as $v) {
            $rate = (float) str_replace(',', '.', (string) $v->Value);
            if ($rate > 0) {
                $rates[] = ['currency' => strtoupper((string) $v->CharCode), 'name' => (string) $v->Name ?: null,
                    'nominal' => max(1, (int) $v->Nominal), 'rate' => $rate, 'change' => null];
            }
        }

        return ['date' => Carbon::createFromFormat('d.m.Y', (string) $doc['Date'])->toDateString(), 'rates' => $rates];
    }
}
