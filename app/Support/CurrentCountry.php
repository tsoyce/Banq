<?php

namespace App\Support;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

/**
 * A21 · Текущая страна витрины (scoped — одна на запрос).
 * Порядок: ?country=kz в URL → cookie banq_country → настройка country.default → первая активная.
 * Ставит App\Http\Middleware\ResolveCountry; читают каталоги (Market, DepositCatalog, RatingBoard…) и шаблоны.
 */
class CurrentCountry
{
    public const COOKIE = 'banq_country';

    private ?Country $country = null;

    private ?Collection $all = null;

    private bool $resolved = false;

    /** Активные страны (для переключателя). */
    public function all(): Collection
    {
        if ($this->all !== null) {
            return $this->all;
        }
        try {
            return $this->all = Installation::isInstalled() ? Country::query()->active()->ordered()->get() : collect();
        } catch (Throwable) {
            return $this->all = collect();
        }
    }

    public function resolve(?Request $request = null): ?Country
    {
        $all = $this->all();
        $pick = fn (?string $slug) => $slug ? $all->first(fn (Country $c) => $c->slug === strtolower($slug) || $c->code === strtoupper($slug)) : null;

        $this->country = $pick($request?->query('country'))
            ?? $pick($request?->cookie(self::COOKIE))
            ?? $pick((string) settings('country.default', ''))
            ?? $all->first();
        $this->resolved = true;

        return $this->country;
    }

    public function set(?Country $country): void
    {
        $this->country = $country;
        $this->resolved = true;
    }

    public function get(): ?Country
    {
        return $this->resolved ? $this->country : $this->resolve(request());
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    /** Основная валюта страны (KZT, если стран ещё нет). */
    public function currency(): string
    {
        return $this->get()?->currency ?? 'KZT';
    }

    public function symbol(): string
    {
        return $this->get()?->symbol() ?? '₸';
    }

    /** Есть ли выбор: переключатель показываем, только когда стран больше одной. */
    public function hasChoice(): bool
    {
        return $this->all()->count() > 1;
    }
}
