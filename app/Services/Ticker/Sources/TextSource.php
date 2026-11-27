<?php

namespace App\Services\Ticker\Sources;

use App\Models\Country;
use App\Services\Ticker\TickerSource;

/** A40 · Лента: свой текст из админки — каждая строка отдельным сообщением. */
class TextSource implements TickerSource
{
    public function label(): string { return 'Свой текст'; }

    public function hint(): string { return 'Объявления и новости вручную: каждая строка — отдельное сообщение'; }

    public function group(?Country $country): ?array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) settings('ticker.text', '')))));

        return $lines ? ['title' => null, 'href' => null, 'items' => array_map(fn ($l) => ['label' => $l], $lines)] : null;
    }
}
