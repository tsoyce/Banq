<?php

namespace App\Services\Exchange\Entities;

use App\Models\PaymentSystem;
use App\Services\Exchange\Entity;
use App\Services\Exchange\ImportContext;
use App\Services\Exchange\RowError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A31 · Платёжные системы: ключ — code (VISA, MASTERCARD…). Внутрь можно положить tiers (уровни карт). */
class PaymentSystemEntity extends Entity
{
    public function key(): string
    {
        return 'payment_systems';
    }

    public function label(): string
    {
        return 'Платёжные системы';
    }

    public function description(): string
    {
        return 'Справочник платёжных систем — международный, без страны. Ключ — code. Внутрь системы можно положить tiers (уровни карт).';
    }

    public function aliases(): array
    {
        return ['payment_system', 'systems', 'платёжные системы', 'платежные системы', 'пс'];
    }

    public function nested(): array
    {
        return ['card_tiers' => ['card_tiers', 'tiers', 'уровни']];
    }

    public function childContext(array $row): array
    {
        return ['payment_system' => $row['code'] ?? $row['name'] ?? null];
    }

    public function fields(): array
    {
        return [
            'code' => ['string', 'desc' => 'код системы латиницей', 'example' => 'VISA', 'rules' => 'max:32', 'aliases' => ['код']],
            'name' => ['string', 'desc' => 'название (обязательно для новой)', 'example' => 'Visa', 'rules' => 'max:160', 'aliases' => ['название']],
            'short_name' => ['string', 'rules' => 'max:80'],
            'aliases' => ['list', 'desc' => 'как ещё пишут систему — для импорта', 'example' => ['China UnionPay']],
            'slug' => ['string', 'desc' => 'для URL; по умолчанию из кода', 'rules' => 'max:64|alpha_dash'],
            'scope' => ['enum', 'values' => array_keys(PaymentSystem::SCOPES), 'desc' => 'international — международная, regional — региональная, national — национальная', 'example' => 'international'],
            'home_country' => ['string', 'desc' => 'ISO-2 страны происхождения', 'example' => 'US', 'rules' => 'size:2'],
            'countries' => ['list', 'desc' => 'ISO-2 стран, где система национальная', 'example' => ['KG']],
            'color' => ['string', 'example' => '#1A1F71', 'rules' => 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_2' => ['string', 'desc' => 'второй цвет градиента', 'rules' => 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['string', 'desc' => 'знак системы: путь /uploads/… или URL', 'rules' => 'max:255'],
            'logo_alt' => ['string', 'desc' => 'второй вариант знака (латиница/кириллица, монохром)', 'rules' => 'max:255'], // A54
            'website' => ['string', 'rules' => 'url|max:255', 'aliases' => ['сайт']],
            'founded_year' => ['int', 'rules' => 'integer|min:1800|max:2100'],
            'headquarters' => ['string', 'rules' => 'max:255'],
            'wallets' => ['list', 'desc' => 'кошельки: '.implode(', ', array_keys(config('banq.cards.wallets'))), 'example' => ['apple_pay', 'google_pay']],
            'description' => ['text'],
            'meta' => ['json', 'desc' => '{mark: «MC»} — буквы марки и прочее'],
            'is_active' => ['bool'],
            'sort' => ['int'],
        ];
    }

    public function find(array $row, ImportContext $ctx): ?Model
    {
        $id = $row['code'] ?? $row['name'] ?? throw new RowError('нужен code или name');

        return PaymentSystem::query()->where('code', Str::upper($id))->first() ?? PaymentSystem::findByIdentifier($id);
    }

    public function attributes(array $row, ImportContext $ctx, ?Model $existing): array
    {
        if (! $existing) {
            $row['name'] ?? throw new RowError('для новой системы нужен name');
            $row['code'] ??= Str::upper(Str::slug($row['name'], ''));
        }

        return $row;
    }

    public function newModel(): Model
    {
        return new PaymentSystem;
    }

    public function rowLabel(array $row): string
    {
        return (string) ($row['code'] ?? $row['name'] ?? '?');
    }

    public function exportQuery(array $filters): Builder
    {
        return PaymentSystem::query()->ordered();
    }

    public function toRow(Model $m): array
    {
        return $this->exportFields($m);
    }
}
