<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A30 · Платёжная система (Visa, Mastercard, UnionPay, Мир, Элкарт…). Международная сущность:
 * страны нет, есть home_country (происхождение) и countries (где система национальная).
 * Уровни карт — CardTier, карты банков — через card_networks.
 */
class PaymentSystem extends Model
{
    public const SCOPES = ['international' => 'Международная', 'regional' => 'Региональная', 'national' => 'Национальная'];

    protected $guarded = ['id'];

    protected $casts = ['countries' => 'array', 'aliases' => 'array', 'wallets' => 'array', 'meta' => 'array', 'is_active' => 'boolean', 'founded_year' => 'integer', 'sort' => 'integer'];

    protected static function booted(): void
    {
        static::saving(function (PaymentSystem $ps) {
            $ps->code = Str::upper(trim($ps->code));
            $ps->slug = Str::lower($ps->slug ?: Str::slug($ps->short_name ?: $ps->name));
            $ps->home_country = $ps->home_country ? Str::upper($ps->home_country) : null;
            $ps->countries = $ps->countries ? array_values(array_unique(array_map('strtoupper', $ps->countries))) : null;
        });
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(CardTier::class)->orderBy('level')->orderBy('sort');
    }

    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'card_networks')->withPivot('card_tier_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort')->orderBy('name');
    }

    public function displayName(): string
    {
        return $this->short_name ?: $this->name;
    }

    public function scopeLabel(): string
    {
        return self::SCOPES[$this->scope] ?? $this->scope;
    }

    /** CSS-градиент марки системы. */
    public function gradient(): string
    {
        $a = $this->color ?: '#6d28d9';

        return 'linear-gradient(135deg, '.$a.', '.($this->color_2 ?: $a).')';
    }

    /** Буквы для круглой марки: VISA, MC, UP. */
    public function mark(): string
    {
        return Str::limit($this->meta['mark'] ?? $this->code, 4, '');
    }

    /** По коду, slug или имени — для импорта. */
    public static function findByIdentifier(string $value): ?self
    {
        $n = Bank::normalize($value);

        return static::query()->get()->first(fn (self $p) => in_array($n, array_map(Bank::normalize(...), array_filter([$p->code, $p->slug, $p->name, $p->short_name, ...($p->aliases ?? [])])), true));
    }
}
