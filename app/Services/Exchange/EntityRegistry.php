<?php

namespace App\Services\Exchange;

use App\Services\Exchange\Entities\BankEntity;
use App\Services\Exchange\Entities\CardEntity;
use App\Services\Exchange\Entities\CardTierEntity;
use App\Services\Exchange\Entities\CountryEntity;
use App\Services\Exchange\Entities\DepositProductEntity;
use App\Services\Exchange\Entities\DepositRateEntity;
use App\Services\Exchange\Entities\PaymentSystemEntity;
use App\Services\Exchange\Entities\PostCategoryEntity;
use App\Services\Exchange\Entities\PostEntity;
use App\Services\Exchange\Entities\ServicePackageEntity;

/**
 * A22 · Реестр сущностей обмена данными. Порядок = порядок импорта (родители раньше детей).
 * Новый раздел (карты, кредиты…) = новый класс Entity + строка здесь — и он сразу доступен
 * в админке, CLI (banq:import / banq:export / banq:schema) и API.
 */
class EntityRegistry
{
    /** @var array<string, Entity> */
    private array $entities = [];

    public function __construct()
    {
        // A31 · карты: платёжные системы → уровни → карты (ссылаются на системы) → пакеты (ссылаются на карты)
        foreach ([new CountryEntity, new BankEntity, new DepositProductEntity, new DepositRateEntity,
            new PaymentSystemEntity, new CardTierEntity, new CardEntity, new ServicePackageEntity,
            new PostCategoryEntity, new PostEntity] as $e) { // A37 · блог: рубрики → публикации (ссылаются на банки и продукты)
            $this->entities[$e->key()] = $e;
        }
    }

    /** @return array<string, Entity> */
    public function all(): array
    {
        return $this->entities;
    }

    public function keys(): array
    {
        return array_keys($this->entities);
    }

    public function get(string $key): Entity
    {
        return $this->find($key) ?? throw new \InvalidArgumentException("Неизвестная сущность «{$key}». Есть: ".implode(', ', $this->keys()));
    }

    /** По ключу, алиасу или названию (имя листа Excel «Банки» → banks). */
    public function find(?string $name): ?Entity
    {
        if ($name === null || $name === '') {
            return null;
        }
        $n = Entity::headerKey($name);
        foreach ($this->entities as $key => $e) {
            if (in_array($n, array_map(Entity::headerKey(...), [$key, $e->label(), ...$e->aliases()]), true)) {
                return $e;
            }
        }

        return null;
    }

    public function schema(): array
    {
        return [
            'version' => 1,
            'about' => 'TSOY.IN Banq — формат обмена данными. Пакет JSON: {"countries": [...], "banks": [...], "deposit_products": [...], "deposit_rates": [...], "payment_systems": [...], "card_tiers": [...], "cards": [...], "service_packages": [...], "post_categories": [...], "posts": [...]}; '
                .'строки ищутся по естественным ключам, повторная заливка не плодит дубли. Банк можно вложить в страну (banks), вклад — в банк (deposit_products), ставки — во вклад (rates), карты и пакеты — в банк (cards, service_packages), уровни — в платёжную систему (tiers). '
                .'CSV/XLSX: заголовки = имена полей, вложенный JSON — через точку (deposit_guarantee.limits.term), списки — через «|». В XLSX лист = сущность.',
            'order' => $this->keys(),
            'entities' => array_map(fn (Entity $e) => $e->schema(), $this->entities),
            'example' => [
                'countries' => [(new CountryEntity)->example()],
                'banks' => [['country' => 'KZ', 'code' => 'BCC', 'name' => 'Банк ЦентрКредит', 'short_name' => 'BCC', 'website' => 'https://www.bcc.kz',
                    'deposit_products' => [['name' => 'Депозит «Срочный»', 'type' => 'term', 'min_amount' => 50000,
                        'rates' => [['currency' => 'KZT', 'term_months' => 12, 'rate' => 14.5, 'effective_rate' => 15.4]]]]]],
            ],
        ];
    }
}
