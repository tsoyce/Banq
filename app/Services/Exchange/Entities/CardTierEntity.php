<?php

namespace App\Services\Exchange\Entities;

use App\Models\CardTier;
use App\Models\PaymentSystem;
use App\Services\Exchange\Entity;
use App\Services\Exchange\ImportContext;
use App\Services\Exchange\RowError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A31 · Уровни карт платёжных систем: ключ — payment_system + code. */
class CardTierEntity extends Entity
{
    public function key(): string
    {
        return 'card_tiers';
    }

    public function label(): string
    {
        return 'Уровни карт';
    }

    public function description(): string
    {
        return 'Уровни (продукты) платёжных систем: Visa Gold, Mastercard World Elite… Ключ — payment_system + code. level 1–6 — ступень для сравнения.';
    }

    public function aliases(): array
    {
        return ['tiers', 'уровни карт', 'уровни'];
    }

    public function fields(): array
    {
        return [
            'payment_system' => ['ref', 'required' => true, 'desc' => 'код или имя платёжной системы', 'example' => 'VISA', 'aliases' => ['система', 'ps']],
            'code' => ['string', 'desc' => 'код уровня латиницей', 'example' => 'infinite', 'rules' => 'max:48', 'aliases' => ['код']],
            'name' => ['string', 'desc' => 'название (обязательно для нового)', 'example' => 'Visa Infinite', 'rules' => 'max:160', 'aliases' => ['название']],
            'slug' => ['string', 'rules' => 'max:120|alpha_dash'],
            'level' => ['int', 'desc' => '1 — базовый … 6 — топ', 'example' => 5, 'rules' => 'integer|min:1|max:6', 'aliases' => ['ступень']],
            'segment' => ['enum', 'values' => array_keys(CardTier::SEGMENTS), 'example' => 'elite'],
            'description' => ['text'],
            'privileges' => ['list', 'desc' => 'привилегии уровня', 'example' => ['Консьерж-сервис', 'Бизнес-залы']],
            'meta' => ['json'],
            'is_active' => ['bool'],
            'sort' => ['int'],
        ];
    }

    private function system(array $row): PaymentSystem
    {
        $id = $row['payment_system'] ?? throw new RowError('нужен payment_system');

        return PaymentSystem::findByIdentifier($id) ?? throw new RowError("платёжная система «{$id}» не найдена — добавь её в payment_systems");
    }

    public function find(array $row, ImportContext $ctx): ?Model
    {
        $ps = $this->system($row);
        $id = $row['code'] ?? $row['name'] ?? throw new RowError('нужен code или name');

        return CardTier::findFor($ps, $id);
    }

    public function attributes(array $row, ImportContext $ctx, ?Model $existing): array
    {
        $ps = $this->system($row);
        unset($row['payment_system']);
        if (! $existing) {
            $row['name'] ?? throw new RowError('для нового уровня нужен name');
            $row['code'] ??= $row['name'];
        }

        return ['payment_system_id' => $ps->id] + $row;
    }

    public function newModel(): Model
    {
        return new CardTier;
    }

    public function rowLabel(array $row): string
    {
        return trim(($row['payment_system'] ?? '').' · '.($row['code'] ?? $row['name'] ?? '?'), ' ·');
    }

    public function exportQuery(array $filters): Builder
    {
        return CardTier::query()->with('paymentSystem')->orderBy('payment_system_id')->orderBy('level')->orderBy('sort');
    }

    public function toRow(Model $m): array
    {
        return ['payment_system' => $m->paymentSystem?->code] + $this->exportFields($m);
    }
}
