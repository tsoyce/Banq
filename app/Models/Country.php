<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A21 · Страна портала. Всё национальное — здесь: валюта, регулятор, центробанк,
 * система гарантирования вкладов (КФГД для KZ) и её лимиты по типам вкладов.
 * Текущая страна витрины — App\Support\CurrentCountry.
 */
class Country extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'currencies' => 'array', 'regulator' => 'array', 'central_bank' => 'array',
        'deposit_guarantee' => 'array', 'meta' => 'array', 'is_active' => 'boolean', 'sort' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Country $c) {
            $c->code = Str::upper(trim($c->code));
            $c->currency = Str::upper(trim($c->currency));
            $c->slug = Str::lower($c->slug ?: $c->code);
            $c->currencies = array_values(array_unique(array_map('strtoupper', [$c->currency, ...($c->currencies ?? [])])));
        });
    }

    public function banks(): HasMany
    {
        return $this->hasMany(Bank::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort')->orderBy('name');
    }

    /** «Казахстана» — для заголовков «Банки Казахстана». */
    public function genitive(): string
    {
        return $this->name_genitive ?: $this->name;
    }

    /** «в Казахстане». */
    public function locative(): string
    {
        return $this->name_locative ?: 'в стране «'.$this->name.'»';
    }

    public function symbol(): string
    {
        return $this->currency_symbol ?: $this->currency;
    }

    /** Валюты вкладов этой страны (основная — первой). */
    public function currencyList(): array
    {
        return $this->currencies ?: [$this->currency];
    }

    /** Короткое имя системы гарантирования: «КФГД». null — системы нет. */
    public function guaranteeName(): ?string
    {
        return $this->deposit_guarantee['short'] ?? $this->deposit_guarantee['name'] ?? null;
    }

    /**
     * Предел гарантии для вклада: по типу (savings/term/demand) в нацвалюте, `foreign` — для иностранной.
     * Сумма — в нацвалюте страны. null — гарантии нет или лимит не задан.
     */
    public function guaranteeLimit(string $type, ?string $currency = null): ?int
    {
        $limits = $this->deposit_guarantee['limits'] ?? [];
        $key = $currency !== null && strtoupper($currency) !== $this->currency ? 'foreign' : $type;
        $v = $limits[$key] ?? null;

        return $v === null || $v === '' ? null : (int) $v;
    }

    public function regulatorName(): ?string
    {
        return $this->regulator['short'] ?? $this->regulator['name'] ?? null;
    }

    public function centralBankName(): ?string
    {
        return $this->central_bank['short'] ?? $this->central_bank['name'] ?? null;
    }

    /** Поиск по коду ISO (KZ), slug, имени или англ. имени — для импорта. */
    public static function findByIdentifier(?string $value): ?self
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }

        return static::query()->where('code', strtoupper($v))->orWhere('slug', strtolower($v))
            ->orWhere('name', $v)->orWhere('name_en', $v)->first();
    }
}
