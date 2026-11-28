<?php

namespace App\Support;

use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A46 · Страна в админке: фильтр в верхней плашке и страны сотрудника.
 * users.admin_countries — id стран, в которых работает сотрудник (null/пусто — все страны).
 * Выбор в плашке (session admin.country) сужает списки, правку и выгрузки до одной страны.
 * Ресурс говорит, где у него страна (Resource::countryScope(): 'country_id', 'bank.country_id'…),
 * ResourceController фильтрует списки, не открывает чужое (404) и не даёт сохранить запись в чужую страну.
 */
class AdminScope
{
    public const SESSION = 'admin.country';

    private function user(): ?User
    {
        return auth()->user();
    }

    /** id стран сотрудника; null — без ограничений. */
    public function allowed(): ?array
    {
        $ids = $this->user()?->admin_countries;

        return $ids ? array_values(array_map('intval', (array) $ids)) : null;
    }

    public function isRestricted(): bool
    {
        return $this->allowed() !== null;
    }

    /** Страны, доступные сотруднику (для плашки). */
    public function countries(): Collection
    {
        return $this->countryQuery()->get();
    }

    /** Запрос стран, доступных сотруднику, — для списков выбора в формах и фильтрах. */
    public function countryQuery(): Builder
    {
        return Country::query()->ordered()->when($this->allowed(), fn ($q, $ids) => $q->whereIn('id', $ids));
    }

    /** Выбранная в плашке страна; у сотрудника с одной страной — всегда она. */
    public function selected(): ?Country
    {
        $all = $this->countries();
        if ($this->isRestricted() && $all->count() === 1) {
            return $all->first();
        }
        $id = (int) session(self::SESSION);

        return $id ? $all->firstWhere('id', $id) : null;
    }

    public function select(?int $id): void
    {
        $id && $this->countries()->contains('id', $id) ? session([self::SESSION => $id]) : session()->forget(self::SESSION);
    }

    /** id стран, которыми сейчас ограничены данные; null — все. */
    public function ids(): ?array
    {
        $sel = $this->selected();

        return $sel ? [$sel->id] : $this->allowed();
    }

    /** Коды стран для выгрузок и таблицы: выбранная страна — по умолчанию. */
    public function code(): ?string
    {
        return $this->selected()?->code;
    }

    /**
     * Код страны для выгрузки/таблицы/импорта по просьбе сотрудника: без ограничений — как просили;
     * с ограничениями — только своя страна (чужая или «все» → выбранная в плашке или первая своя).
     */
    public function clampCode(?string $code): ?string
    {
        $code = $code ? strtoupper($code) : null;
        if (! $this->isRestricted()) {
            return $code;
        }
        $mine = $this->countries()->pluck('code');

        return $code !== null && $mine->contains($code) ? $code : ($this->code() ?? $mine->first());
    }

    /**
     * Ограничить запрос странами. $path — где страна у модели: 'id' (сами страны), 'country_id', 'bank.country_id', 'product.bank.country_id'.
     * $nullable — запись без страны («для всех стран», как у публикаций) видна всем.
     */
    public function apply(Builder $q, ?string $path, bool $nullable = false, bool $selection = true): Builder
    {
        $ids = $selection ? $this->ids() : $this->allowed();
        if ($path === null || $ids === null) {
            return $q;
        }
        if (! str_contains($path, '.')) {
            $col = $q->getModel()->qualifyColumn($path);

            return $q->where(fn ($w) => $w->whereIn($col, $ids)->when($nullable, fn ($n) => $n->orWhereNull($col)));
        }

        return $q->whereHas(Str::beforeLast($path, '.'), fn ($r) => $r->whereIn($r->getModel()->qualifyColumn(Str::afterLast($path, '.')), $ids));
    }

    /** Можно ли сотруднику сохранить запись с такой страной (проверка по правам, не по выбору в плашке). */
    public function permits(Model $m, ?string $path, bool $nullable = false): bool
    {
        $allowed = $this->allowed();
        if ($path === null || $allowed === null) {
            return true;
        }
        $id = data_get($m, $path);

        return $id === null ? $nullable : in_array((int) $id, $allowed, true);
    }
}
