<?php

namespace App\Services\Import;

use App\Enums\DepositType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * A08 · Разбор Excel/CSV со ставками по вкладам. Ничего не пишет в БД.
 *
 * Понимает два формата:
 *  1. «Матрица» (как в первом файле пользователя): банки по одной оси,
 *     типы вкладов («Снятие и пополнение», «Пополнение», «Табу») — по другой.
 *     Ориентация определяется сама: типы в строке-заголовке или в столбце.
 *     Если заголовок типа объединён на 2 ячейки — первая = ставка, вторая = ГЭСВ
 *     (так устроен лист «Лист2» файла deposits.xlsx).
 *  2. «Плоская таблица»: строка заголовков с колонками Банк, Ставка и
 *     необязательными Тип, Продукт, Срок, Валюта, Мин. сумма.
 *
 * Пустые ячейки и нули — «нет данных», не ставка 0 %.
 * Ячейки с процентным форматом (0.142 → «14.20%») переводятся в проценты.
 * Одинаковые записи с разных листов схлопываются; при расхождении побеждает
 * последний лист (с предупреждением). Лишние листы можно отключить ($only).
 */
class DepositSheetParser
{
    private const FLAT_COLUMNS = [
        'bank' => ['банк', 'bank', 'код банка', 'код'],
        'rate' => ['ставка', 'rate', 'ставка, %', '%'],
        'type' => ['тип', 'тип вклада', 'type'],
        'product' => ['продукт', 'вклад', 'депозит', 'название', 'product'],
        'term' => ['срок', 'срок, мес', 'срок (мес)', 'term', 'месяцев'],
        'currency' => ['валюта', 'currency'],
        'min_amount' => ['мин. сумма', 'минимальная сумма', 'сумма от', 'min'],
        'effective_rate' => ['гэсв', 'эффективная ставка', 'effective'],
    ];

    /** Объединённые ячейки текущего листа: "r:c" (0-based) → [ширина, высота]. */
    private array $spans = [];

    /**
     * @param  list<string>|null  $only  какие листы читать (null — все)
     * @return array{records: list<array>, warnings: list<string>, sheets: array<string,int>}
     */
    public function parse(string $path, ?array $only = null): array
    {
        $book = IOFactory::load($path);
        $records = [];
        $warnings = [];
        $sheets = [];

        foreach ($book->getAllSheets() as $sheet) {
            $name = $sheet->getTitle();
            if ($only !== null && ! in_array($name, $only, true)) {
                continue;
            }
            $grid = $this->grid($sheet);
            $found = $this->parseFlat($grid, $name) ?? $this->parseMatrix($grid, $name);

            if ($found === null) {
                $warnings[] = "Лист «{$name}»: не нашёл ни заголовков типов вкладов, ни колонок Банк/Ставка — пропущен.";

                continue;
            }
            $sheets[$name] = count($found);
            array_push($records, ...$found);
        }

        return ['records' => $this->dedupe($records, $warnings), 'warnings' => $warnings, 'sheets' => $sheets];
    }

    /** Таблица значений листа; процентный формат → проценты (0.142 → 14.2). */
    private function grid(Worksheet $sheet): array
    {
        $this->spans = [];
        foreach ($sheet->getMergeCells() as $range) {
            [[$c1, $r1], [$c2, $r2]] = Coordinate::rangeBoundaries($range);
            $this->spans[($r1 - 1).':'.($c1 - 1)] = [$c2 - $c1 + 1, $r2 - $r1 + 1];
        }

        $grid = [];
        foreach ($sheet->getRowIterator() as $row) {
            $r = $row->getRowIndex() - 1;
            $cells = $row->getCellIterator();
            $cells->setIterateOnlyExistingCells(true);
            foreach ($cells as $cell) {
                $c = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
                $v = $cell->getCalculatedValue();
                if (is_numeric($v) && ! is_string($v) && str_contains($cell->getStyle()->getNumberFormat()->getFormatCode(), '%')) {
                    $v = round((float) $v * 100, 4);
                }
                $grid[$r][$c] = $v;
            }
        }
        foreach ($grid as &$line) {
            ksort($line);
        }
        ksort($grid);

        return $grid;
    }

    private function parseMatrix(array $grid, string $sheet): ?array
    {
        // Ищем ячейки с названиями типов вкладов.
        $hits = [];
        foreach ($grid as $r => $row) {
            foreach ($row as $c => $cell) {
                if (is_string($cell) && ($type = DepositType::fromLabel($cell))) {
                    $hits[] = [$r, $c, $type];
                }
            }
        }
        if (count($hits) === 0) {
            return null;
        }

        $byRow = array_count_values(array_map(fn ($h) => $h[0], $hits));
        $byCol = array_count_values(array_map(fn ($h) => $h[1], $hits));
        $typesInRow = max($byRow) >= max($byCol);

        $records = [];
        if ($typesInRow) {
            $headerRow = array_search(max($byRow), $byRow, true);
            $typeCols = [];
            foreach ($hits as [$r, $c, $t]) {
                if ($r === $headerRow) {
                    $typeCols[$c] = $t;
                }
            }
            $dataCols = array_keys($typeCols);
            foreach (array_keys($typeCols) as $c) {
                if (($this->spans[$headerRow.':'.$c][0] ?? 1) >= 2) {
                    $dataCols[] = $c + 1; // вторая ячейка объединения — ГЭСВ
                }
            }
            foreach ($grid as $r => $row) {
                if ($r <= $headerRow || ! ($bank = $this->bankIn($row, $dataCols))) {
                    continue;
                }
                foreach ($typeCols as $c => $type) {
                    if ($rate = $this->rate($row[$c] ?? null)) {
                        $eff = ($this->spans[$headerRow.':'.$c][0] ?? 1) >= 2 ? $this->rate($row[$c + 1] ?? null) : null;
                        $records[] = $this->record($bank, $type, $rate, $sheet, ['effective_rate' => $eff]);
                    }
                }
            }
        } else {
            $headerCol = array_search(max($byCol), $byCol, true);
            $typeRows = [];
            foreach ($hits as [$r, $c, $t]) {
                if ($c === $headerCol) {
                    $typeRows[$r] = $t;
                }
            }
            // Банки — в строке над первым типом.
            $bankRow = min(array_keys($typeRows)) - 1;
            foreach ($grid[$bankRow] ?? [] as $c => $cell) {
                if ($c === $headerCol || ! ($bank = $this->bankName($cell))) {
                    continue;
                }
                foreach ($typeRows as $r => $type) {
                    if ($rate = $this->rate($grid[$r][$c] ?? null)) {
                        $eff = ($this->spans[$r.':'.$headerCol][1] ?? 1) >= 2 ? $this->rate($grid[$r + 1][$c] ?? null) : null;
                        $records[] = $this->record($bank, $type, $rate, $sheet, ['effective_rate' => $eff]);
                    }
                }
            }
        }

        return $records;
    }

    private function parseFlat(array $grid, string $sheet): ?array
    {
        foreach (array_slice($grid, 0, 10, true) as $r => $row) {
            $map = [];
            foreach ($row as $c => $cell) {
                $key = is_string($cell) ? mb_strtolower(trim($cell)) : null;
                foreach (self::FLAT_COLUMNS as $field => $names) {
                    if ($key !== null && in_array($key, $names, true) && ! isset($map[$field])) {
                        $map[$field] = $c;
                    }
                }
            }
            if (! isset($map['bank'], $map['rate'])) {
                continue;
            }

            $records = [];
            foreach (array_slice($grid, $r + 1, null, true) as $line) {
                $bank = $this->bankName($line[$map['bank']] ?? null);
                $rate = $this->rate($line[$map['rate']] ?? null);
                if (! $bank || ! $rate) {
                    continue;
                }
                $type = isset($map['type']) ? DepositType::fromLabel((string) $line[$map['type']]) : null;
                $records[] = $this->record($bank, $type ?? DepositType::Term, $rate, $sheet, [
                    'product' => isset($map['product']) ? (trim((string) $line[$map['product']]) ?: null) : null,
                    'term_months' => isset($map['term']) ? ($this->int($line[$map['term']]) ?: null) : null,
                    'currency' => isset($map['currency']) ? (strtoupper(trim((string) $line[$map['currency']])) ?: 'KZT') : 'KZT',
                    'min_amount' => isset($map['min_amount']) ? $this->number($line[$map['min_amount']]) : null,
                    'effective_rate' => isset($map['effective_rate']) ? $this->rate($line[$map['effective_rate']]) : null,
                ]);
            }

            return $records;
        }

        return null;
    }

    private function record(string $bank, DepositType $type, float $rate, string $sheet, array $extra = []): array
    {
        return array_merge([
            'bank' => $bank,
            'type' => $type,
            'rate' => $rate,
            'effective_rate' => null,
            'currency' => 'KZT',
            'term_months' => null,
            'min_amount' => null,
            'product' => null,
            'sheet' => $sheet,
        ], $extra);
    }

    /** Первая текстовая ячейка строки (кроме колонок с данными) — идентификатор банка. */
    private function bankIn(array $row, array $skipCols): ?string
    {
        foreach ($row as $c => $cell) {
            if (! in_array($c, $skipCols, true) && ($name = $this->bankName($cell))) {
                return $name;
            }
        }

        return null;
    }

    private function bankName(mixed $cell): ?string
    {
        if (! is_string($cell)) {
            return null;
        }
        $v = trim($cell);
        if ($v === '' || is_numeric(str_replace(',', '.', $v)) || DepositType::fromLabel($v)) {
            return null;
        }

        return $v;
    }

    private function rate(mixed $cell): ?float
    {
        $n = $this->number($cell);

        return $n === null || $n <= 0 ? null : round($n, 3);
    }

    private function number(mixed $cell): ?float
    {
        if (is_int($cell) || is_float($cell)) {
            return (float) $cell;
        }
        if (is_string($cell)) {
            $v = str_replace([' ', "\u{00A0}", '%', ','], ['', '', '', '.'], $cell);

            return is_numeric($v) ? (float) $v : null;
        }

        return null;
    }

    private function int(mixed $cell): ?int
    {
        $n = $this->number($cell);

        return $n === null ? null : (int) round($n);
    }

    private function dedupe(array $records, array &$warnings): array
    {
        $out = [];
        foreach ($records as $rec) {
            $key = implode('|', [mb_strtoupper($rec['bank']), $rec['type']->value, $rec['currency'], $rec['term_months'], $rec['min_amount'], $rec['product']]);
            if (isset($out[$key]) && $out[$key]['rate'] !== $rec['rate']) {
                $warnings[] = "{$rec['bank']} / {$rec['type']->label()}: на листах «{$out[$key]['sheet']}» и «{$rec['sheet']}» разные ставки ({$out[$key]['rate']} и {$rec['rate']}). Взята последняя.";
            }
            $out[$key] = $rec;
        }

        return array_values($out);
    }
}
