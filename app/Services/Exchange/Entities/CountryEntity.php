<?php

namespace App\Services\Exchange\Entities;

use App\Models\Country;
use App\Services\Exchange\Entity;
use App\Services\Exchange\ImportContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A22 · Страны: ключ — ISO-код (KZ). */
class CountryEntity extends Entity
{
    public function key(): string
    {
        return 'countries';
    }

    public function label(): string
    {
        return 'Страны';
    }

    public function description(): string
    {
        return 'Страны портала: валюта, регулятор, центробанк, система гарантирования вкладов и её лимиты. Ключ — code (ISO 3166-1 alpha-2).';
    }

    public function aliases(): array
    {
        return ['country', 'страны'];
    }

    public function nested(): array
    {
        return ['banks' => ['banks', 'банки']];
    }

    public function childContext(array $row): array
    {
        return ['country' => $row['code']];
    }

    public function fields(): array
    {
        return [
            'code' => ['string', 'required' => true, 'desc' => 'ISO-код страны', 'example' => 'KZ', 'rules' => 'size:2|alpha', 'aliases' => ['iso', 'код']],
            'slug' => ['string', 'desc' => 'для URL (?country=kz); по умолчанию — code строчными', 'example' => 'kz', 'rules' => 'max:32|alpha_dash'],
            'name' => ['string', 'required' => true, 'desc' => 'название по-русски', 'example' => 'Казахстан', 'rules' => 'max:120', 'aliases' => ['название', 'страна']],
            'name_genitive' => ['string', 'desc' => 'родительный падеж: «банки Казахстана»', 'example' => 'Казахстана'],
            'name_locative' => ['string', 'desc' => 'предложный: «в Казахстане»', 'example' => 'в Казахстане'],
            'name_en' => ['string', 'example' => 'Kazakhstan'],
            'name_native' => ['string', 'example' => 'Қазақстан'],
            'flag' => ['string', 'desc' => 'эмодзи флага', 'example' => '🇰🇿', 'rules' => 'max:16'],
            'currency' => ['string', 'required' => true, 'desc' => 'основная валюта, ISO 4217', 'example' => 'KZT', 'rules' => 'size:3|alpha', 'aliases' => ['валюта']],
            'currency_symbol' => ['string', 'example' => '₸', 'rules' => 'max:8'],
            'currencies' => ['list', 'desc' => 'валюты вкладов по порядку показа', 'example' => ['KZT', 'USD', 'EUR']],
            'locale' => ['string', 'example' => 'ru', 'rules' => 'max:10'],
            'timezone' => ['string', 'example' => 'Asia/Almaty', 'rules' => 'timezone'],
            'phone_code' => ['string', 'example' => '+7', 'rules' => 'max:8'],
            'regulator' => ['json', 'desc' => 'регулятор: {short, name, url}', 'example' => ['short' => 'АРРФР', 'name' => 'Агентство по регулированию и развитию финансового рынка', 'url' => 'https://www.gov.kz/memleket/entities/ardfm']],
            'central_bank' => ['json', 'desc' => 'центробанк: {short, name, url, base_rate}', 'example' => ['short' => 'НБРК', 'name' => 'Национальный Банк Казахстана', 'url' => 'https://nationalbank.kz']],
            'deposit_guarantee' => ['json', 'desc' => 'гарантирование вкладов: {short, name, url, limits: {savings, term, demand, foreign}} — лимиты в нацвалюте; foreign — для вкладов в иностранной валюте', 'example' => ['short' => 'КФГД', 'url' => 'https://kdif.kz', 'limits' => ['savings' => 20000000, 'term' => 10000000, 'demand' => 10000000, 'foreign' => 5000000]]],
            'meta' => ['json', 'desc' => 'любые дополнительные данные'],
            'is_active' => ['bool', 'desc' => 'видна на сайте', 'example' => true],
            'sort' => ['int', 'example' => 10],
        ];
    }

    public function find(array $row, ImportContext $ctx): ?Model
    {
        return Country::query()->where('code', strtoupper($row['code']))->first();
    }

    public function attributes(array $row, ImportContext $ctx, ?Model $existing): array
    {
        $row['code'] = strtoupper($row['code']);
        if (isset($row['deposit_guarantee'], $existing?->deposit_guarantee)) {
            // частичное обновление JSON: присланные ключи поверх существующих
            $row['deposit_guarantee'] = array_replace_recursive($existing->deposit_guarantee, $row['deposit_guarantee']);
        }

        return $row;
    }

    public function newModel(): Model
    {
        return new Country;
    }

    public function rowLabel(array $row): string
    {
        return strtoupper((string) ($row['code'] ?? '?')).(isset($row['name']) ? ' · '.$row['name'] : '');
    }

    public function exportQuery(array $filters): Builder
    {
        return Country::query()->ordered()->when($filters['country'] ?? null, fn ($q, $c) => $q->where('code', strtoupper($c)));
    }

    public function toRow(Model $m): array
    {
        return $this->exportFields($m);
    }
}
