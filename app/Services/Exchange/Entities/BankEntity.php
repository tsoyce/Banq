<?php

namespace App\Services\Exchange\Entities;

use App\Models\Bank;
use App\Services\Exchange\Entity;
use App\Services\Exchange\ImportContext;
use App\Services\Exchange\RowError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A22 · Банки: ключ — страна + код (или имя/алиас, если кода нет). */
class BankEntity extends Entity
{
    public function key(): string
    {
        return 'banks';
    }

    public function label(): string
    {
        return 'Банки';
    }

    public function description(): string
    {
        return 'Справочник банков. Ключ — country + code; если code не указан, банк ищется по имени и алиасам. Внутрь банка можно положить deposit_products.';
    }

    public function aliases(): array
    {
        return ['bank', 'банки'];
    }

    public function nested(): array
    {
        return ['deposit_products' => ['deposit_products', 'products', 'deposits', 'вклады'],
            // A31 · карты и пакеты услуг внутри банка
            'cards' => ['cards', 'карты'], 'service_packages' => ['service_packages', 'packages', 'пакеты']];
    }

    public function childContext(array $row): array
    {
        return ['bank' => $row['code'] ?? $row['name'] ?? null] + (isset($row['country']) ? ['country' => $row['country']] : []);
    }

    public function fields(): array
    {
        return [
            'country' => ['ref', 'desc' => 'код страны (по умолчанию — страна импорта)', 'example' => 'KZ', 'aliases' => ['страна', 'country_code']],
            'code' => ['string', 'desc' => 'короткий код банка (BCC, FFIN…); если нет — сгенерируется из имени', 'example' => 'BCC', 'rules' => 'max:32', 'aliases' => ['код']],
            'name' => ['string', 'desc' => 'полное название (обязательно для нового банка)', 'example' => 'Банк ЦентрКредит', 'rules' => 'max:160', 'aliases' => ['название', 'банк']],
            'short_name' => ['string', 'example' => 'BCC', 'rules' => 'max:80', 'aliases' => ['короткое имя']],
            'name_kz' => ['string', 'rules' => 'max:160'],
            'slug' => ['string', 'desc' => 'для URL; по умолчанию из имени', 'rules' => 'max:120|alpha_dash'],
            'aliases' => ['list', 'desc' => 'как ещё пишут банк — для поиска при импорте', 'example' => ['ЦентрКредит', 'CenterCredit']],
            'legal_name' => ['string', 'rules' => 'max:255'],
            'bin' => ['string', 'desc' => 'регистрационный номер (БИН в KZ)', 'rules' => 'max:12'],
            'license' => ['string', 'rules' => 'max:255'],
            'swift' => ['string', 'rules' => 'max:11'],
            'logo' => ['string', 'desc' => 'путь или URL логотипа', 'rules' => 'max:255'],
            'brand_color' => ['string', 'example' => '#00A0E3', 'rules' => 'regex:/^#[0-9a-fA-F]{6}$/'],
            'website' => ['string', 'example' => 'https://www.bcc.kz', 'rules' => 'url|max:255', 'aliases' => ['сайт']],
            'phone' => ['string', 'rules' => 'max:32'],
            'founded_year' => ['int', 'example' => 1988, 'rules' => 'integer|min:1700|max:2100'],
            'assets_bn' => ['decimal', 'desc' => 'активы, млрд в нацвалюте', 'example' => 4500.5],
            'capital_bn' => ['decimal', 'desc' => 'собственный капитал, млрд в нацвалюте'],
            'financials_at' => ['date', 'desc' => 'на какую дату финансы', 'example' => '2026-10-01'],
            'deposit_insured' => ['bool', 'desc' => 'участник системы гарантирования вкладов страны', 'example' => true, 'aliases' => ['is_kdif_member', 'кфгд']],
            'description' => ['text'],
            'status' => ['enum', 'values' => ['active', 'revoked', 'merged'], 'example' => 'active', 'desc' => 'active — работает, revoked — лицензия отозвана, merged — присоединён'],
            'sort' => ['int'],
        ];
    }

    public function find(array $row, ImportContext $ctx): ?Model
    {
        $country = $ctx->country($row['country'] ?? null);
        $id = $row['code'] ?? $row['name'] ?? $row['short_name'] ?? null;
        if ($id === null) {
            throw new RowError('нужен code или name');
        }
        if (isset($row['code']) && ($bank = Bank::query()->where('country_id', $country->id)->where('code', Str::upper($row['code']))->first())) {
            return $bank;
        }

        return Bank::findByIdentifier($id, $country);
    }

    public function attributes(array $row, ImportContext $ctx, ?Model $existing): array
    {
        $country = $ctx->country($row['country'] ?? null);
        unset($row['country']);
        if (! $existing && empty($row['name'])) {
            throw new RowError('для нового банка нужен name');
        }
        if (! $existing && empty($row['code'])) {
            $row['code'] = Str::limit(Str::upper(Str::slug($row['short_name'] ?? $row['name'], '')), 32, '');
        }
        if (! $existing && empty($row['slug'])) {
            $row['slug'] = self::uniqueSlug(Str::slug($row['short_name'] ?? $row['name']), $country->code);
        }

        return ['country_id' => $country->id] + $row;
    }

    /** Slug уникален глобально: при совпадении в другой стране добавляем код страны. */
    private static function uniqueSlug(string $slug, string $countryCode): string
    {
        $slug = $slug ?: 'bank';
        if (! Bank::query()->where('slug', $slug)->exists()) {
            return $slug;
        }
        $base = $slug.'-'.strtolower($countryCode);
        for ($i = 0, $s = $base; Bank::query()->where('slug', $s)->exists(); $s = $base.'-'.++$i);

        return $s;
    }

    public function newModel(): Model
    {
        return new Bank;
    }

    public function rowLabel(array $row): string
    {
        return trim(($row['country'] ?? '').' · '.($row['code'] ?? $row['name'] ?? '?'), ' ·');
    }

    public function exportQuery(array $filters): Builder
    {
        return Bank::query()->with('country')->orderBy('country_id')->orderBy('sort')->orderBy('name')
            ->when($filters['country'] ?? null, fn ($q, $c) => $q->whereHas('country', fn ($w) => $w->where('code', strtoupper($c))))
            ->when($filters['bank'] ?? null, fn ($q, $b) => $q->whereIn('code', array_map('strtoupper', (array) $b)));
    }

    public function toRow(Model $m): array
    {
        return ['country' => $m->country?->code] + $this->exportFields($m);
    }
}
