<?php

namespace App\Enums;

/** A18 · Три вида рейтинга банка — показываются рядом на /ratings и в карточке банка. */
enum RatingKind: string
{
    case Author = 'author';       // мнение автора портала (выставляется в админке)
    case Objective = 'objective'; // расчёт из данных: ставки, линейка, условия, надёжность
    case People = 'people';       // народный: оценки из одобренных отзывов

    public function label(): string
    {
        return match ($this) {
            self::Author => 'Авторский',
            self::Objective => 'Объективный',
            self::People => 'Народный',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Author => 'Мнение редакции TSOY.IN',
            self::Objective => 'Расчёт по ставкам и надёжности',
            self::People => 'Оценки клиентов банка',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Author => 'pen',
            self::Objective => 'chart',
            self::People => 'users',
        };
    }

    /** Цвет серии на графиках и гейджах (DESIGN §2.4: народный — тёплый оранж). */
    public function color(): string
    {
        return match ($this) {
            self::Author => '#8b5cf6',
            self::Objective => '#06b6d4',
            self::People => '#f97316',
        };
    }
}
