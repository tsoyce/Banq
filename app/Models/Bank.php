<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Bank extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_kdif_member' => 'boolean', 'founded_year' => 'integer', 'aliases' => 'array'];

    protected static function booted(): void
    {
        static::saving(function (Bank $bank) {
            $bank->code = Str::upper(trim($bank->code));
            $bank->slug ??= Str::slug($bank->short_name ?: $bank->name);
        });
    }

    public function depositProducts(): HasMany
    {
        return $this->hasMany(DepositProduct::class);
    }

    public function depositRates(): HasManyThrough
    {
        return $this->hasManyThrough(DepositRate::class, DepositProduct::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function displayName(): string
    {
        return $this->short_name ?: $this->name;
    }

    /** Поиск по коду (BCC), имени или алиасу (Halyk, Homecredit…) — для импорта. Без учёта регистра, пробелов и дефисов. */
    public static function findByIdentifier(string $value): ?self
    {
        $needle = static::normalize($value);

        return static::query()->get()->first(fn (Bank $b) => in_array($needle, array_map(
            static::normalize(...),
            array_filter([$b->code, $b->short_name, $b->name, ...($b->aliases ?? [])])
        ), true));
    }

    public static function normalize(string $v): string
    {
        return preg_replace('/[\s\-_.«»"\']+/u', '', mb_strtolower(trim($v)));
    }
}
