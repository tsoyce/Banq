<?php

namespace App\Services\Exchange\Entities;

use App\Enums\DepositType;
use App\Models\DepositProduct;
use App\Models\DepositRate;
use App\Services\Exchange\Entity;
use App\Services\Exchange\ImportContext;
use App\Services\Exchange\RowError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A22 · Ставки вкладов. Ставки НЕ перезаписываются (правило 5 CLAUDE.md):
 *  - строка без valid_to — «текущая ставка»: если такая же уже действует → unchanged;
 *    если другая → старая закрывается (valid_to = valid_from новой), добавляется новая (updated);
 *  - строка с valid_to — историческая запись: добавляется, если записи с тем же ключом и valid_from ещё нет.
 * Ключ ставки: вклад + валюта + срок + порог суммы (amount_from).
 */
class DepositRateEntity extends Entity
{
    public function __construct(private DepositProductEntity $products = new DepositProductEntity) {}

    public function key(): string
    {
        return 'deposit_rates';
    }

    public function label(): string
    {
        return 'Ставки';
    }

    public function description(): string
    {
        return 'Ставки по вкладам с историей. Вклад — product (slug) или bank + product (название) [+ type]. Новая ставка закрывает старую — история не теряется. Ставки — в процентах годовых (14.5, «14,5%»).';
    }

    public function aliases(): array
    {
        return ['rates', 'ставки'];
    }

    public function fields(): array
    {
        return [
            'country' => ['ref', 'desc' => 'код страны банка (если вклад задан через bank)', 'example' => 'KZ', 'aliases' => ['страна']],
            'bank' => ['ref', 'desc' => 'код/имя банка — если product задан названием', 'example' => 'BCC', 'aliases' => ['банк']],
            'product' => ['ref', 'required' => true, 'desc' => 'slug вклада или его название (тогда нужен bank)', 'example' => 'bcc-depozit-srochnyj', 'aliases' => ['вклад', 'продукт', 'product_slug', 'deposit_product']],
            'type' => ['enum', 'values' => ['demand', 'term', 'savings'], 'parse' => fn ($s) => DepositType::fromLabel($s)?->value, 'desc' => 'тип вклада — уточняет поиск по названию', 'aliases' => ['тип']],
            'currency' => ['string', 'desc' => 'ISO 4217; по умолчанию — валюта страны банка', 'example' => 'KZT', 'rules' => 'size:3|alpha', 'aliases' => ['валюта']],
            'term_months' => ['int', 'desc' => 'срок, мес.; пусто — любой срок', 'example' => 12, 'rules' => 'integer|min:0|max:600', 'aliases' => ['срок', 'term']],
            'amount_from' => ['decimal', 'desc' => 'порог суммы «от»; пусто — любая сумма', 'aliases' => ['сумма от', 'min_amount']],
            'amount_to' => ['decimal', 'desc' => 'порог суммы «до»', 'aliases' => ['сумма до']],
            'rate' => ['decimal', 'required' => true, 'desc' => 'номинальная ставка, % годовых', 'example' => 14.5, 'rules' => 'numeric|min:0|max:500', 'aliases' => ['ставка']],
            'effective_rate' => ['decimal', 'desc' => 'эффективная ставка (ГЭСВ), %', 'example' => 15.4, 'rules' => 'numeric|min:0|max:500', 'aliases' => ['гэсв', 'эффективная']],
            'valid_from' => ['date', 'desc' => 'с какой даты действует; по умолчанию — сегодня', 'example' => '2026-11-27', 'aliases' => ['с', 'дата']],
            'valid_to' => ['date', 'desc' => 'до какой даты (только для исторических записей)'],
            'source' => ['enum', 'values' => ['manual', 'import', 'parser', 'api'], 'desc' => 'источник; по умолчанию — import'],
        ];
    }

    public function product(array $row, ImportContext $ctx): DepositProduct
    {
        $ref = $row['product'];
        $bySlug = DepositProduct::query()->where('slug', $ref)->with('bank.country')->first();
        if ($bySlug) {
            return $bySlug;
        }
        if (! isset($row['bank'])) {
            throw new RowError("вклад «{$ref}» не найден по slug — укажи bank, чтобы искать по названию");
        }
        $bank = $this->products->bank($row, $ctx);

        return $bank->depositProducts()->where('name', $ref)->when($row['type'] ?? null, fn ($q, $t) => $q->where('type', $t))->with('bank.country')->first()
            ?? throw new RowError("у банка {$bank->code} нет вклада «{$ref}»");
    }

    public function find(array $row, ImportContext $ctx): ?Model
    {
        return null; // своя логика в persist()
    }

    public function attributes(array $row, ImportContext $ctx, ?Model $existing): array
    {
        $product = $this->product($row, $ctx);

        return [
            'deposit_product_id' => $product->id,
            'currency' => strtoupper($row['currency'] ?? $product->bank->country?->currency ?? 'KZT'),
            'term_months' => $row['term_months'] ?? null,
            'amount_from' => $row['amount_from'] ?? null,
            'amount_to' => $row['amount_to'] ?? null,
            'rate' => $row['rate'],
            'effective_rate' => $row['effective_rate'] ?? null,
            'valid_from' => $row['valid_from'] ?? today()->toDateString(),
            'valid_to' => $row['valid_to'] ?? null,
            'source' => $row['source'] ?? ($ctx->options->source === 'api' ? 'api' : 'import'),
        ];
    }

    public function persist(array $row, ImportContext $ctx): array
    {
        $a = $this->attributes($row, $ctx, null);
        $same = fn (Builder $q) => $q->where('deposit_product_id', $a['deposit_product_id'])->where('currency', $a['currency'])
            ->where(fn ($w) => $a['term_months'] === null ? $w->whereNull('term_months') : $w->where('term_months', $a['term_months']))
            ->where(fn ($w) => $a['amount_from'] === null ? $w->whereNull('amount_from') : $w->where('amount_from', $a['amount_from']));

        // историческая запись
        if ($a['valid_to'] !== null) {
            if ($same(DepositRate::query())->whereDate('valid_from', $a['valid_from'])->exists()) {
                return ['unchanged', [], null];
            }
            if ($ctx->options->mode === 'update') {
                return ['skipped', [], 'историческая запись — новая (режим «только обновить»)'];
            }
            DepositRate::create($a + ['import_id' => $ctx->importId]);

            return ['created', [], null];
        }

        $current = $same(DepositRate::query())->whereNull('valid_to')->latest('valid_from')->first();
        if ($current && self::num($current->rate) === self::num($a['rate']) && self::num($current->effective_rate) === self::num($a['effective_rate'])
            && self::num($current->amount_to) === self::num($a['amount_to'])) {
            return ['unchanged', [], null];
        }
        if ($current && $ctx->options->mode === 'create') {
            return ['skipped', [], 'ставка уже есть (режим «только новые»)'];
        }
        if (! $current && $ctx->options->mode === 'update') {
            return ['skipped', [], 'такой ставки ещё нет (режим «только обновить»)'];
        }
        if ($current && Carbon::parse($a['valid_from'])->lt($current->valid_from)) {
            throw new RowError("valid_from {$a['valid_from']} раньше действующей ставки ({$current->valid_from->toDateString()}) — передай её как историческую с valid_to");
        }

        $changes = [];
        if ($current) {
            $changes = ['rate' => [(float) $current->rate, (float) $a['rate']]];
            if (self::num($current->effective_rate) !== self::num($a['effective_rate'])) {
                $changes['effective_rate'] = [self::num($current->effective_rate), self::num($a['effective_rate'])];
            }
            $current->update(['valid_to' => $a['valid_from']]);
        }
        DepositRate::create($a + ['import_id' => $ctx->importId]);

        return [$current ? 'updated' : 'created', $changes, null];
    }

    private static function num(mixed $v): ?float
    {
        return $v === null ? null : round((float) $v, 3);
    }

    public function newModel(): Model
    {
        return new DepositRate;
    }

    public function rowLabel(array $row): string
    {
        return implode(' · ', array_filter([
            $row['product'] ?? '?', $row['currency'] ?? null, isset($row['term_months']) ? $row['term_months'].' мес' : null,
            isset($row['amount_from']) ? 'от '.$row['amount_from'] : null,
        ]));
    }

    public function exportQuery(array $filters): Builder
    {
        return DepositRate::query()->with('product.bank.country')->orderBy('deposit_product_id')->orderBy('currency')->orderBy('term_months')->orderBy('valid_from')
            ->when($filters['current'] ?? false, fn ($q) => $q->whereNull('valid_to'))
            ->when($filters['country'] ?? null, fn ($q, $c) => $q->whereHas('product.bank.country', fn ($w) => $w->where('code', strtoupper($c))))
            ->when($filters['bank'] ?? null, fn ($q, $b) => $q->whereHas('product.bank', fn ($w) => $w->whereIn('code', array_map('strtoupper', (array) $b))));
    }

    public function toRow(Model $m): array
    {
        return ['country' => $m->product?->bank?->country?->code, 'bank' => $m->product?->bank?->code, 'product' => $m->product?->slug]
            + $this->exportFields($m, ['type']);
    }
}
