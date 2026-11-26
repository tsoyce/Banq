<?php

namespace App\Services\Import;

use App\Enums\DepositType;
use App\Models\Bank;
use App\Models\DepositProduct;
use App\Models\Import;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A08 · Запись разобранных ставок в БД.
 * Новая ставка закрывает старую (valid_to = сегодня) — история сохраняется.
 * Если ставка не изменилась — строка пропускается.
 */
class DepositImporter
{
    public function __construct(private DepositSheetParser $parser) {}

    /** Предпросмотр: записи + какие банки не найдены. */
    public function preview(string $path): array
    {
        $parsed = $this->parser->parse($path);
        $codes = collect($parsed['records'])->pluck('bank')->unique();
        $known = $codes->mapWithKeys(fn ($c) => [$c => Bank::findByIdentifier($c)]);

        $parsed['banks'] = $known->map(fn (?Bank $b) => $b?->displayName())->all();
        $parsed['unknown'] = $known->filter(fn ($b) => $b === null)->keys()->values()->all();

        return $parsed;
    }

    /** @param list<string>|null $sheets какие листы импортировать (null — все) */
    public function import(string $path, string $filename, ?int $userId = null, bool $createBanks = false, ?array $sheets = null): Import
    {
        $parsed = $this->parser->parse($path, $sheets);
        $import = Import::create(['user_id' => $userId, 'kind' => 'deposits', 'filename' => $filename, 'status' => 'running']);
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $log = $parsed['warnings'];

        DB::transaction(function () use ($parsed, $import, $createBanks, &$stats, &$log) {
            foreach ($parsed['records'] as $rec) {
                $bank = Bank::findByIdentifier($rec['bank']);
                if (! $bank && $createBanks) {
                    $bank = Bank::create(['code' => Str::upper(Str::slug($rec['bank'], '')) ?: $rec['bank'], 'name' => $rec['bank'], 'short_name' => $rec['bank'], 'slug' => Str::slug($rec['bank'])]);
                    $log[] = "Создан банк «{$rec['bank']}» — заполни карточку в админке.";
                }
                if (! $bank) {
                    $stats['skipped']++;
                    $log[] = "Банк «{$rec['bank']}» не найден — строка пропущена.";

                    continue;
                }

                $product = $this->product($bank, $rec['type'], $rec['product']);
                $current = $product->rates()->current()
                    ->where('currency', $rec['currency'])
                    ->where('term_months', $rec['term_months'])
                    ->where('amount_from', $rec['min_amount'])
                    ->latest('valid_from')->first();

                if ($current && (float) $current->rate === $rec['rate'] && ($current->effective_rate === null ? null : (float) $current->effective_rate) === $rec['effective_rate']) {
                    $stats['skipped']++;

                    continue;
                }
                if ($current) {
                    $current->update(['valid_to' => today()]);
                    $stats['updated']++;
                } else {
                    $stats['created']++;
                }

                $product->rates()->create([
                    'currency' => $rec['currency'],
                    'term_months' => $rec['term_months'],
                    'amount_from' => $rec['min_amount'],
                    'rate' => $rec['rate'],
                    'effective_rate' => $rec['effective_rate'],
                    'valid_from' => today(),
                    'source' => 'import',
                    'import_id' => $import->id,
                ]);
            }
        });

        $import->update([
            'rows_total' => count($parsed['records']),
            'rows_created' => $stats['created'],
            'rows_updated' => $stats['updated'],
            'rows_skipped' => $stats['skipped'],
            'log' => $log,
            'status' => 'done',
        ]);

        return $import;
    }

    private function product(Bank $bank, DepositType $type, ?string $name): DepositProduct
    {
        $name ??= $type->label().' депозит';

        return DepositProduct::firstOrCreate(
            ['bank_id' => $bank->id, 'type' => $type->value, 'name' => $name],
            [
                'slug' => Str::slug($bank->slug.'-'.$name.'-'.$type->value),
                'currencies' => ['KZT'],
                'can_replenish' => $type->canReplenish(),
                'can_withdraw' => $type->canWithdraw(),
            ]
        );
    }
}
