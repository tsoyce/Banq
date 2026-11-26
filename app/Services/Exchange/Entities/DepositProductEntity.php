<?php

namespace App\Services\Exchange\Entities;

use App\Enums\DepositType;
use App\Models\Bank;
use App\Models\DepositProduct;
use App\Services\Exchange\Entity;
use App\Services\Exchange\ImportContext;
use App\Services\Exchange\RowError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A22 · Вклады (продукты): ключ — slug, иначе банк + название. Внутрь можно положить rates. */
class DepositProductEntity extends Entity
{
    public function key(): string
    {
        return 'deposit_products';
    }

    public function label(): string
    {
        return 'Вклады';
    }

    public function description(): string
    {
        return 'Депозитные продукты банков. Ключ — slug; если его нет — bank (+country) + name. Внутрь продукта можно положить rates.';
    }

    public function aliases(): array
    {
        return ['products', 'deposits', 'вклады', 'депозиты'];
    }

    public function nested(): array
    {
        return ['deposit_rates' => ['deposit_rates', 'rates', 'ставки']];
    }

    public function childContext(array $row): array
    {
        return array_filter(['product' => $row['slug'] ?? $row['name'] ?? null, 'bank' => $row['bank'] ?? null, 'country' => $row['country'] ?? null, 'type' => $row['type'] ?? null]);
    }

    public function fields(): array
    {
        return [
            'country' => ['ref', 'desc' => 'код страны банка (по умолчанию — страна импорта)', 'example' => 'KZ', 'aliases' => ['страна']],
            'bank' => ['ref', 'desc' => 'код, имя или алиас банка', 'example' => 'BCC', 'aliases' => ['банк', 'bank_code']],
            'slug' => ['string', 'desc' => 'ключ продукта и URL; по умолчанию банк + название + тип', 'example' => 'bcc-depozit-srochnyj', 'rules' => 'max:160|alpha_dash'],
            'name' => ['string', 'desc' => 'название вклада (обязательно для нового)', 'example' => 'Депозит «Срочный»', 'rules' => 'max:160', 'aliases' => ['название', 'продукт', 'product']],
            'type' => ['enum', 'values' => array_map(fn ($t) => $t->value, DepositType::cases()), 'parse' => fn ($s) => DepositType::fromLabel($s)?->value,
                'desc' => 'demand — несрочный (снятие и пополнение), term — срочный (только пополнение), savings — сберегательный (без операций); понимает и русские подписи', 'example' => 'term', 'aliases' => ['тип']],
            'currencies' => ['list', 'desc' => 'валюты вклада', 'example' => ['KZT', 'USD'], 'aliases' => ['валюты']],
            'min_amount' => ['decimal', 'example' => 50000, 'aliases' => ['мин. сумма', 'минимальная сумма']],
            'max_amount' => ['decimal', 'aliases' => ['макс. сумма']],
            'term_min' => ['int', 'desc' => 'мин. срок, мес.', 'example' => 3],
            'term_max' => ['int', 'desc' => 'макс. срок, мес.', 'example' => 36],
            'capitalization' => ['enum', 'values' => ['monthly', 'end', 'none'], 'desc' => 'monthly — ежемесячно, end — в конце срока, none — выплата'],
            'can_replenish' => ['bool', 'desc' => 'можно пополнять (по умолчанию — по типу)', 'aliases' => ['пополнение']],
            'can_withdraw' => ['bool', 'desc' => 'можно частично снимать (по умолчанию — по типу)', 'aliases' => ['снятие']],
            'online_opening' => ['bool', 'example' => true, 'aliases' => ['онлайн']],
            'description' => ['text'],
            'conditions' => ['json', 'desc' => 'прочие условия ключ → значение'],
            'source_url' => ['string', 'desc' => 'страница продукта на сайте банка', 'rules' => 'url|max:255', 'aliases' => ['источник', 'url']],
            'is_active' => ['bool', 'example' => true],
        ];
    }

    public function find(array $row, ImportContext $ctx): ?Model
    {
        if (isset($row['slug']) && ($p = DepositProduct::query()->where('slug', $row['slug'])->first())) {
            return $p;
        }
        if (! isset($row['name'])) {
            return isset($row['slug']) ? null : throw new RowError('нужен slug или bank + name');
        }

        return DepositProduct::query()->where('bank_id', $this->bank($row, $ctx)->id)->where('name', $row['name'])
            ->when($row['type'] ?? null, fn ($q, $t) => $q->where('type', $t))->first();
    }

    public function bank(array $row, ImportContext $ctx): Bank
    {
        $id = $row['bank'] ?? throw new RowError('нужен bank — код, имя или алиас банка');
        $country = $ctx->country($row['country'] ?? null);

        return Bank::query()->where('country_id', $country->id)->where('code', Str::upper($id))->first()
            ?? Bank::findByIdentifier($id, $country)
            ?? throw new RowError("банк «{$id}» не найден в стране {$country->code}");
    }

    public function attributes(array $row, ImportContext $ctx, ?Model $existing): array
    {
        $bank = isset($row['bank']) ? $this->bank($row, $ctx) : ($existing?->bank ?? throw new RowError('нужен bank'));
        unset($row['country'], $row['bank']);

        if (! $existing) {
            $type = DepositType::tryFrom($row['type'] ?? '') ?? throw new RowError('для нового вклада нужен type');
            $row['name'] ?? throw new RowError('для нового вклада нужен name');
            $row['slug'] ??= self::uniqueSlug(Str::slug($bank->slug.'-'.$row['name'].'-'.$type->value));
            $row['can_replenish'] ??= $type->canReplenish();
            $row['can_withdraw'] ??= $type->canWithdraw();
            $row['currencies'] ??= [$bank->country?->currency ?? 'KZT'];
        }

        return ['bank_id' => $bank->id] + $row;
    }

    private static function uniqueSlug(string $slug): string
    {
        for ($i = 1, $s = $slug; DepositProduct::query()->where('slug', $s)->exists(); $s = $slug.'-'.++$i);

        return $s;
    }

    public function newModel(): Model
    {
        return new DepositProduct;
    }

    public function rowLabel(array $row): string
    {
        return $row['slug'] ?? trim(($row['bank'] ?? '').' · '.($row['name'] ?? '?'), ' ·');
    }

    public function exportQuery(array $filters): Builder
    {
        return DepositProduct::query()->with('bank.country')->orderBy('bank_id')->orderBy('name')
            ->when($filters['country'] ?? null, fn ($q, $c) => $q->whereHas('bank.country', fn ($w) => $w->where('code', strtoupper($c))))
            ->when($filters['bank'] ?? null, fn ($q, $b) => $q->whereHas('bank', fn ($w) => $w->whereIn('code', array_map('strtoupper', (array) $b))));
    }

    public function toRow(Model $m): array
    {
        return ['country' => $m->bank?->country?->code, 'bank' => $m->bank?->code] + $this->exportFields($m);
    }
}
