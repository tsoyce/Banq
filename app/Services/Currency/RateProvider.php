<?php

namespace App\Services\Currency;

use Carbon\CarbonInterface;

/**
 * A40 · Источник официальных курсов одной страны (центробанк).
 * Новая страна = новый класс + строка в config('banq.fx.providers').
 */
interface RateProvider
{
    /** Короткий ключ для таблицы currency_rates.source: nbrk. */
    public function key(): string;

    /** «НБРК» — для подписи в ленте и карточке. */
    public function short(): string;

    /** «Национальный Банк Казахстана». */
    public function name(): string;

    /** Публичная страница курсов — ссылка «Источник». */
    public function url(): string;

    /**
     * Курсы на дату. Источник может вернуть ближайшую прошлую дату (выходные) — она в `date`.
     *
     * @return array{date: string, rates: list<array{currency: string, name: ?string, nominal: int, rate: float, change: ?float}>}
     *
     * @throws \RuntimeException источник недоступен или ответ не разобран
     */
    public function fetch(CarbonInterface $date): array;
}
