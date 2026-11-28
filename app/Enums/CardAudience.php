<?php

namespace App\Enums;

/** A54 · Для кого карта: физическим лицам или бизнесу (юрлица и ИП). */
enum CardAudience: string
{
    case Personal = 'personal';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Физлицам',
            self::Business => 'Бизнесу',
        };
    }

    /** «для физлиц» — в подписях и заголовках, со строчной. */
    public function forWhom(): string
    {
        return match ($this) {
            self::Personal => 'для физлиц',
            self::Business => 'для бизнеса',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Personal => 'Личные карты: зарплата, покупки, путешествия',
            self::Business => 'Корпоративные и бизнес-карты для юрлиц и ИП',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Personal => 'user',
            self::Business => 'layers',
        };
    }

    /** Понимает подписи из таблиц: «физлица», «юр. лица», «ИП», «бизнес», «корпоративная». */
    public static function fromLabel(?string $s): ?self
    {
        $s = mb_strtolower(trim((string) $s));
        if ($s === '') {
            return null;
        }

        return self::tryFrom($s) ?? match (true) {
            (bool) preg_match('/^(физ|личн|частн|retail|individual|person)/u', $s) => self::Personal,
            (bool) preg_match('/^(юр|бизн|корпор|ип\b|ип$|предпр|business|corporate|sme|legal)/u', $s) => self::Business,
            default => null,
        };
    }
}
