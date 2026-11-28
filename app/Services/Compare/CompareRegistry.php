<?php

namespace App\Services\Compare;

use App\Services\Compare\Types\BankCompare;
use App\Services\Compare\Types\CardCompare;
use App\Services\Compare\Types\DepositCompare;
use App\Services\Compare\Types\PackageCompare;
use App\Services\Compare\Types\PaymentSystemCompare;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * A53 · Реестр видов сравнения. Новый вид продукта = класс CompareType + строка в TYPES
 * + хранилище в resources/js/app.js (COMPARE_TYPES: store, storageKey, path — те же, что в классе).
 * Сравниваются продукты одного вида; у каждого вида свой список до limit() штук.
 */
class CompareRegistry
{
    public const TYPES = [
        'deposits' => DepositCompare::class,
        'cards' => CardCompare::class,
        'packages' => PackageCompare::class,
        'banks' => BankCompare::class,
        'payment-systems' => PaymentSystemCompare::class,
    ];

    /** Виды, которых ещё нет в базе: вкладка «скоро». */
    public const SOON = [
        'loans' => ['Кредиты', 'loan'],
    ];

    /** Раздел сайта → вид сравнения для панели внизу и счётчика. */
    private const ROUTES = [
        'cards.*' => 'cards',
        'packages.*' => 'packages',
        'banks.*' => 'banks',
        'ratings' => 'banks',
        'payment-systems.*' => 'payment-systems',
    ];

    private array $resolved = [];

    /** @return Collection<string, CompareType> */
    public function all(): Collection
    {
        return collect(self::TYPES)->map(fn ($class, $key) => $this->get($key));
    }

    public function get(string $key): ?CompareType
    {
        if (! isset(self::TYPES[$key])) {
            return null;
        }

        return $this->resolved[$key] ??= app(self::TYPES[$key]);
    }

    public function has(string $key): bool
    {
        return isset(self::TYPES[$key]);
    }

    /** Вид сравнения для текущей страницы: раздел сайта или /compare/{type}; по умолчанию — вклады. */
    public function forRequest(Request $request): CompareType
    {
        $param = $request->route()?->parameter('type');
        if (is_string($param) && $this->has($param)) {
            return $this->get($param);
        }
        foreach (self::ROUTES as $pattern => $key) {
            if ($request->routeIs($pattern)) {
                return $this->get($key);
            }
        }

        return $this->get('deposits');
    }
}
