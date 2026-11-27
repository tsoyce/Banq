<?php

namespace App\Enums;

/** A30 · Вид банковской карты. */
enum CardKind: string
{
    case Debit = 'debit';
    case Credit = 'credit';
    case Prepaid = 'prepaid';

    public function label(): string
    {
        return match ($this) {
            self::Debit => 'Дебетовая',
            self::Credit => 'Кредитная',
            self::Prepaid => 'Предоплаченная',
        };
    }

    /** «дебетовые карты» — для заголовков. */
    public function plural(): string
    {
        return match ($this) {
            self::Debit => 'Дебетовые',
            self::Credit => 'Кредитные',
            self::Prepaid => 'Предоплаченные',
        };
    }

    /** A36 · «среди дебетовых карт» — родительный падеж множественного числа, со строчной. */
    public function genitivePlural(): string
    {
        return match ($this) {
            self::Debit => 'дебетовых',
            self::Credit => 'кредитных',
            self::Prepaid => 'предоплаченных',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Debit => 'Свои деньги, кэшбэк, процент на остаток',
            self::Credit => 'Деньги банка, льготный период',
            self::Prepaid => 'Подарочные и виртуальные с лимитом',
        };
    }

    /** Цвет серии на графиках и чипах. */
    public function color(): string
    {
        return match ($this) {
            self::Debit => 'var(--brand-2)',
            self::Credit => 'var(--brand-1)',
            self::Prepaid => 'var(--brand-3)',
        };
    }

    /** Понимает русские подписи из таблиц: «кредитка», «дебетовая». */
    public static function fromLabel(?string $s): ?self
    {
        $s = mb_strtolower(trim((string) $s));
        if ($s === '') {
            return null;
        }

        return self::tryFrom($s) ?? match (true) {
            str_starts_with($s, 'дебет') => self::Debit,
            str_starts_with($s, 'кредит') => self::Credit,
            str_starts_with($s, 'предопл'), str_starts_with($s, 'подароч') => self::Prepaid,
            default => null,
        };
    }
}
