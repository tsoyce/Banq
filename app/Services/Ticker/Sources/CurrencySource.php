<?php

namespace App\Services\Ticker\Sources;

use App\Models\Country;
use App\Services\Currency\CurrencyRates;
use App\Services\Ticker\TickerSource;

/** A40 · Лента: официальные курсы центробанка страны (валюты — из админки «Курсы валют»). */
class CurrencySource implements TickerSource
{
    public function __construct(private CurrencyRates $fx) {}

    public function label(): string { return 'Курсы валют'; }

    public function hint(): string { return 'Официальный курс центробанка страны (для Казахстана — НБРК) и изменение за день'; }

    public function group(?Country $country): ?array
    {
        $this->fx->ensureFresh($country);
        $board = $this->fx->board($country);
        if (! $board || ! $board['items']) {
            return null;
        }

        return [
            'flag' => $country->code, // A46 · флаг страны у подписи группы
            'title' => $board['provider']->short().($board['date'] ? ' · '.$board['date']->translatedFormat('j M') : ''),
            'href' => $board['provider']->url(),
            'items' => array_map(fn ($i) => [
                'label' => ($i['nominal'] > 1 ? $i['nominal'].' ' : '').$i['code'],
                'value' => number_format($i['rate'], 2, ',', ' ').' '.$board['symbol'],
                'delta' => $i['delta'],
                'flag' => $i['flag'] ?? null, // A46
            ], $board['items']),
        ];
    }
}
