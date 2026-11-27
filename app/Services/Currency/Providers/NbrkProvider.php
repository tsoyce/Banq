<?php

namespace App\Services\Currency\Providers;

use App\Services\Currency\RateProvider;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A40 · Национальный Банк Казахстана: XML-фид официальных курсов
 * https://nationalbank.kz/rss/get_rates.cfm?fdate=27.11.2026
 * <rates><date>27.11.2026</date><item><title>USD</title><description>458.85</description><quant>1</quant><change>+0.28</change>…
 * Курс в тенге за <quant> единиц. Та же таблица — https://nationalbank.kz/ru/exchangerates/ezhednevnye-oficialnye-rynochnye-kursy-valyut
 */
class NbrkProvider implements RateProvider
{
    public const URL = 'https://nationalbank.kz/rss/get_rates.cfm';

    public function key(): string { return 'nbrk'; }

    public function short(): string { return 'НБРК'; }

    public function name(): string { return 'Национальный Банк Казахстана'; }

    public function url(): string { return 'https://nationalbank.kz/ru/exchangerates/ezhednevnye-oficialnye-rynochnye-kursy-valyut'; }

    public function fetch(CarbonInterface $date): array
    {
        $res = Http::timeout(8)->retry(2, 400, throw: false)->accept('application/xml')
            ->get(self::URL, ['fdate' => $date->format('d.m.Y')]);
        if (! $res->successful()) {
            throw new RuntimeException('НБРК ответил HTTP '.$res->status());
        }

        return self::parse($res->body());
    }

    public static function parse(string $xml): array
    {
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($prev);
        if ($doc === false || ! isset($doc->item)) {
            throw new RuntimeException('НБРК: ответ не похож на ленту курсов');
        }

        $rates = [];
        foreach ($doc->item as $item) {
            $code = strtoupper(trim((string) $item->title));
            $rate = (float) str_replace(',', '.', (string) $item->description);
            if (! preg_match('/^[A-Z]{3}$/', $code) || $rate <= 0) {
                continue;
            }
            $change = trim((string) $item->change);
            $rates[] = [
                'currency' => $code,
                'name' => trim((string) $item->fullname) ?: null,
                'nominal' => max(1, (int) $item->quant),
                'rate' => $rate,
                'change' => $change === '' ? null : (float) str_replace(',', '.', $change),
            ];
        }
        if (! $rates) {
            throw new RuntimeException('НБРК: в ответе нет курсов');
        }

        return ['date' => Carbon::createFromFormat('d.m.Y', trim((string) $doc->date))->toDateString(), 'rates' => $rates];
    }
}
