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

    /** A54 · Металл значка по умолчанию: classic | gold | platinum | black | business | green. */
    public const TONES = ['classic' => 'Классика (сталь)', 'gold' => 'Золото', 'platinum' => 'Платина', 'black' => 'Чёрный премиум', 'business' => 'Бизнес (синий)', 'green' => 'Зелёный'];

    public function isBusiness(): bool
    {
        return $this->segment === 'business';
    }

    /** A54 · Короткое имя уровня без названия системы: «Visa Gold» → «Gold», «Мир Привилегия» → «Привилегия». */
    public function shortName(): string
    {
        $ps = $this->paymentSystem;
        foreach (array_filter([$ps?->name, $ps?->short_name]) as $prefix) {
            if (mb_stripos($this->name, $prefix.' ') === 0) {
                return trim(mb_substr($this->name, mb_strlen($prefix)));
            }
        }

        return $this->name;
    }

    /** A54 · Подпись на значке: своя (badge_text — «MIR Privilege» или «МИР Привилегия»), иначе короткое имя. */
    public function badgeLabel(): string
    {
        return $this->badge_text ?: $this->shortName();
    }

    /** A54 · Загруженный значок (путь /uploads/… или URL); null — рисуем свой SVG. */
    public function badgeUrl(): ?string
    {
        return $this->badge ?: null;
    }

    /** A54 · Металл значка: meta.tone, иначе по коду и ступени. */
    public function tone(): string
    {
        $t = $this->meta['tone'] ?? null;
        if ($t && isset(self::TONES[$t])) {
            return $t;
        }
        $c = (string) $this->code;

        return match (true) {
            $this->isBusiness() || str_contains($c, 'business') || str_contains($c, 'corporate') => 'business',
            str_contains($c, 'green') => 'green',
            str_contains($c, 'gold') => 'gold',
            str_contains($c, 'platinum') => 'platinum',
            $this->level >= 4 => 'black',
            $this->level === 3 => 'platinum',
            $this->level === 2 => 'gold',
            default => 'classic',
        };
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
