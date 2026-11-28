<?php

namespace App\Services\Compare;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A53 · Вид продукта для универсального сравнения: как найти записи по id, как подписать колонку,
 * какие строки показать и где лежит список в браузере ($store.<store()>, localStorage).
 * Вклады и карты сравниваются на своих страницах (deposits/cards.compare) — у них свои строки и калькулятор;
 * остальные виды рисует общая страница compare/show по rows().
 */
abstract class CompareType
{
    /** Ключ в адресе: /compare/{key}. */
    abstract public function key(): string;

    /** «Вклады» — вкладка и плитка. */
    abstract public function label(): string;

    /** «вкладов» — «Сравнение вкладов». */
    abstract public function genitive(): string;

    /** Формы для счёта: ['вклад', 'вклада', 'вкладов']. */
    abstract public function forms(): array;

    abstract public function icon(): string;

    /** Имя Alpine-хранилища списка. */
    abstract public function store(): string;

    /** Ключ localStorage. */
    abstract public function storageKey(): string;

    abstract public function catalogUrl(): string;

    /** Что сравнивается — подпись в шапке и на плитке хаба. */
    abstract public function hint(): string;

    /** @return Collection<int, Model> записи в порядке $ids (неактивные пропускаются) */
    abstract public function items(Collection $ids): Collection;

    /**
     * Шапка колонки и элемент хранилища: id, code (буквы марки), color, name (подпись в панели),
     * title, sub, url — ссылка на карточку.
     */
    abstract public function head(Model $item): array;

    /** Записи, которые хранились, — для сохранённых сравнений (включая снятые с продажи). */
    public function stored(Collection $ids): Collection
    {
        return $this->items($ids);
    }

    /**
     * Строки общей таблицы: ['group' => 'Стоимость'] — подзаголовок или
     * ['label', 'value' => fn ($item): string, 'num' => fn ($item): ?float, 'dir' => 'max'|'min', 'big' => bool].
     */
    public function rows(Collection $items): array
    {
        return [];
    }

    public function note(): ?string
    {
        return null;
    }

    public function limit(): int
    {
        return 4;
    }

    /** Адрес страницы сравнения. Вклады и карты переопределяют — у них свои маршруты. */
    public function url(array $ids = [], array $query = []): string
    {
        return route('compare.show', array_filter(['type' => $this->key()] + array_merge(['ids' => implode(',', $ids)], $query), fn ($v) => $v !== null && $v !== ''));
    }

    /** Путь без хоста — для хранилища в браузере. */
    public function path(): string
    {
        return parse_url($this->url(), PHP_URL_PATH) ?: '/';
    }

    /** Страница сравнения рисуется общим шаблоном compare/show. */
    public function generic(): bool
    {
        return true;
    }

    /** Название подборки по умолчанию: коды банков или имена. */
    public function defaultTitle(Collection $items): string
    {
        return $items->map(fn ($m) => $this->head($m)['code'])->unique()->join(' · ');
    }

    public function count(int $n): string
    {
        return $n.' '.plural($n, ...$this->forms());
    }

    /** Разобрать ids=1,2,3 с ограничением. */
    public function ids(?string $raw): Collection
    {
        return collect(explode(',', (string) $raw))->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->take($this->limit())->values();
    }

    /** Упорядочить найденное как в $ids. */
    protected function ordered(Collection $found, Collection $ids): Collection
    {
        return $found->sortBy(fn ($m) => $ids->search($m->getKey()))->values();
    }
}
