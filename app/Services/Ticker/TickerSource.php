<?php

namespace App\Services\Ticker;

use App\Models\Country;

/**
 * A40 · Тип контента бегущей строки в шапке. Новый тип (новости блога, лучшие карты…) —
 * класс с этим интерфейсом + строка в config('banq.ticker.sources'); в админке он появится сам.
 */
interface TickerSource
{
    /** Название в админке: «Курсы валют». */
    public function label(): string;

    /** Пояснение под названием. */
    public function hint(): string;

    /**
     * Группа ленты для страны: подпись группы и элементы. null/пусто — группа не показывается.
     *
     * @return array{title: ?string, href: ?string, items: list<array{label: string, value?: ?string, delta?: ?float, href?: ?string}>}|null
     */
    public function group(?Country $country): ?array;
}
