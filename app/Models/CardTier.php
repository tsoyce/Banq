<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/** A30 · Уровень карты платёжной системы (Visa Gold, Mastercard World Elite): ступень level 1–6, сегмент, привилегии. */
class CardTier extends Model
{
    public const SEGMENTS = ['mass' => 'Массовый', 'premium' => 'Премиальный', 'elite' => 'Элитный', 'business' => 'Бизнес', 'virtual' => 'Виртуальный'];

    protected $guarded = ['id'];

    protected $casts = ['privileges' => 'array', 'meta' => 'array', 'is_active' => 'boolean', 'level' => 'integer', 'sort' => 'integer'];

    protected static function booted(): void
    {
        static::saving(function (CardTier $t) {
            $t->code = Str::lower(Str::slug($t->code ?: $t->name, '_'));
            if (! $t->slug) {
                $ps = PaymentSystem::find($t->payment_system_id);
                $t->slug = Str::slug(($ps?->slug ?? 'ps').'-'.str_replace('_', '-', $t->code));
            }
        });
    }

    public function paymentSystem(): BelongsTo
    {
        return $this->belongsTo(PaymentSystem::class);
    }

    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'card_networks');
    }

    public function segmentLabel(): string
    {
        return self::SEGMENTS[$this->segment] ?? $this->segment;
    }

    /** Найти уровень системы по коду или имени: «gold», «Visa Gold», «World Elite». */
    public static function findFor(PaymentSystem $ps, string $value): ?self
    {
        $n = Bank::normalize($value);
        $psn = Bank::normalize($ps->name);

        return $ps->tiers()->get()->first(function (self $t) use ($n, $psn) {
            $names = array_map(Bank::normalize(...), [$t->code, $t->slug, $t->name]);
            $short = str_starts_with(Bank::normalize($t->name), $psn) ? substr(Bank::normalize($t->name), strlen($psn)) : null;

            return in_array($n, $names, true) || ($short !== null && $n === $short);
        });
    }
}
