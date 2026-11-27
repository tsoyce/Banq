<?php

namespace App\Support;

/**
 * A40 · Подписи валют: русское название, знак, флаг. Источники пишут названия КАПСОМ
 * («ДОЛЛАР США») — для частых валют берём своё, для прочих — аккуратный регистр.
 */
class CurrencyInfo
{
    private const NAMES = [
        'USD' => 'Доллар США', 'EUR' => 'Евро', 'RUB' => 'Российский рубль', 'CNY' => 'Китайский юань', 'KZT' => 'Казахстанский тенге',
        'GBP' => 'Фунт стерлингов', 'JPY' => 'Японская иена', 'CHF' => 'Швейцарский франк', 'TRY' => 'Турецкая лира', 'AED' => 'Дирхам ОАЭ',
        'UZS' => 'Узбекский сум', 'KGS' => 'Киргизский сом', 'TJS' => 'Таджикский сомони', 'BYN' => 'Белорусский рубль', 'GEL' => 'Грузинский лари',
        'AMD' => 'Армянский драм', 'AZN' => 'Азербайджанский манат', 'KRW' => 'Южнокорейская вона', 'HKD' => 'Гонконгский доллар',
        'CAD' => 'Канадский доллар', 'AUD' => 'Австралийский доллар', 'INR' => 'Индийская рупия', 'XAU' => 'Золото, унция',
    ];

    private const SYMBOLS = [
        'USD' => '$', 'EUR' => '€', 'RUB' => '₽', 'CNY' => '¥', 'KZT' => '₸', 'GBP' => '£', 'JPY' => '¥', 'TRY' => '₺',
        'KRW' => '₩', 'INR' => '₹', 'GEL' => '₾', 'AZN' => '₼', 'AMD' => '֏', 'UZS' => 'сўм', 'KGS' => 'сом', 'BYN' => 'Br', 'CHF' => '₣',
    ];

    public static function name(string $code, ?string $fallback = null): string
    {
        $code = strtoupper($code);
        if (isset(self::NAMES[$code])) {
            return self::NAMES[$code];
        }
        if ($fallback && mb_strtoupper($fallback) === $fallback) {
            $fallback = mb_strtoupper(mb_substr($fallback, 0, 1)).mb_strtolower(mb_substr($fallback, 1));
        }

        return $fallback ?: $code;
    }

    public static function symbol(string $code): string
    {
        return self::SYMBOLS[strtoupper($code)] ?? strtoupper($code);
    }

    /** Флаг по первым двум буквам кода (USD → 🇺🇸, EUR → 🇪🇺). Металлы и SDR (X…) — без флага. */
    public static function flag(string $code): string
    {
        $cc = substr(strtoupper($code), 0, 2);
        if (! preg_match('/^[A-W][A-Z]$/', $cc)) {
            return '';
        }

        return mb_chr(0x1F1E6 + ord($cc[0]) - 65).mb_chr(0x1F1E6 + ord($cc[1]) - 65);
    }
}
