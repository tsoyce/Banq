<?php

namespace App\Models;

use App\Support\CurrentCountry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Bank extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'deposit_insured' => 'boolean', 'founded_year' => 'integer', 'aliases' => 'array',
        'assets_bn' => 'decimal:1', 'capital_bn' => 'decimal:1', 'financials_at' => 'date', // A18
    ];

    protected static function booted(): void
    {
        static::saving(function (Bank $bank) {
            $bank->code = Str::upper(trim($bank->code));
            $bank->slug ??= Str::slug($bank->short_name ?: $bank->name);
            // A21 · банк без страны попадает в страну по умолчанию (первая из списка)
            $bank->country_id ??= Country::query()->orderBy('sort')->value('id');
        });
    }

    /** A21 · Страна банка: валюта, регулятор, система гарантирования вкладов. */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** A21 · Банки страны (id, модель или null = текущая страна витрины). */
    public function scopeInCountry(Builder $q, Country|int|null $country = null): Builder
    {
        $id = $country instanceof Country ? $country->id : ($country ?? app(CurrentCountry::class)->id());

        return $id ? $q->where('country_id', $id) : $q;
    }

    public function depositProducts(): HasMany
    {
        return $this->hasMany(DepositProduct::class);
    }

    public function depositRates(): HasManyThrough
    {
        return $this->hasManyThrough(DepositRate::class, DepositProduct::class);
    }

    /** A18 · История оценок (авторская, снимки объективной и народной). */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /** A18 · Отзывы клиентов (на сайте — только approved). */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** A18 · Код для круглой марки (не длиннее 4 символов). */
    public function mark(): string
    {
        return Str::limit($this->code, 4, '');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function displayName(): string
    {
        return $this->short_name ?: $this->name;
    }

    /**
     * Поиск по коду (BCC), имени или алиасу (Halyk, Homecredit…) — для импорта. Без учёта регистра, пробелов и дефисов.
     * A21: $country — искать только среди банков этой страны (null — во всех).
     */
    public static function findByIdentifier(string $value, Country|int|null $country = null): ?self
    {
        $needle = static::normalize($value);
        $countryId = $country instanceof Country ? $country->id : $country;

        return static::query()->when($countryId, fn ($q) => $q->where('country_id', $countryId))->get()->first(fn (Bank $b) => in_array($needle, array_map(
            static::normalize(...),
            array_filter([$b->code, $b->short_name, $b->name, ...($b->aliases ?? [])])
        ), true));
    }

    public static function normalize(string $v): string
    {
        return preg_replace('/[\s\-_.«»"\']+/u', '', mb_strtolower(trim($v)));
    }
}
