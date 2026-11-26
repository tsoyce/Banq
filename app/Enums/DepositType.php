<?php

namespace App\Enums;

/**
 * A07 · Тип вклада по классификации РК (КФГД).
 * Подписи совпадают с колонками Excel пользователя — см. aliases().
 */
enum DepositType: string
{
    case Demand = 'demand';   // несрочный: пополнение + снятие
    case Term = 'term';       // срочный: только пополнение
    case Savings = 'savings'; // сберегательный: без операций

    public function label(): string
    {
        return match ($this) {
            self::Demand => 'Несрочный',
            self::Term => 'Срочный',
            self::Savings => 'Сберегательный',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Demand => 'Снятие и пополнение',
            self::Term => 'Только пополнение',
            self::Savings => 'Без пополнения и снятия',
        };
    }

    public function canReplenish(): bool
    {
        return $this !== self::Savings;
    }

    public function canWithdraw(): bool
    {
        return $this === self::Demand;
    }

    /** Подписи, по которым импортёр узнаёт тип в заголовках таблицы. */
    public static function aliases(): array
    {
        return [
            'снятие и пополнение' => self::Demand,
            'пополнение и снятие' => self::Demand,
            'несрочный' => self::Demand,
            'demand' => self::Demand,
            'пополнение' => self::Term,
            'только пополнение' => self::Term,
            'срочный' => self::Term,
            'term' => self::Term,
            'табу' => self::Savings,
            'без пополнения и снятия' => self::Savings,
            'сберегательный' => self::Savings,
            'savings' => self::Savings,
        ];
    }

    public static function fromLabel(?string $text): ?self
    {
        if ($text === null) {
            return null;
        }
        $key = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)));

        return self::aliases()[$key] ?? self::tryFrom($key);
    }
}
