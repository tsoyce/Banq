<?php

namespace App\Services\Exchange\Formats;

/** A22 · Общее для CSV/XLSX: вложенное → колонки через точку, списки → «a|b», обратно — строки по заголовку. */
trait Tabular
{
    /** @param list<array> $rows @return array{0: list<string>, 1: list<list<mixed>>} */
    protected function flatten(array $rows): array
    {
        $flat = array_map(fn ($r) => $this->flattenRow($r), $rows);
        $headers = [];
        foreach ($flat as $r) {
            foreach (array_keys($r) as $k) {
                $headers[$k] = true;
            }
        }
        $headers = array_keys($headers);

        return [$headers, array_map(fn ($r) => array_map(fn ($h) => $r[$h] ?? null, $headers), $flat)];
    }

    private function flattenRow(array $row, string $prefix = ''): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $key = $prefix.$k;
            if (is_array($v) && $v !== [] && ! array_is_list($v)) {
                $out += $this->flattenRow($v, $key.'.');
            } elseif (is_array($v)) {
                $out[$key] = collect($v)->every(fn ($x) => is_scalar($x)) ? implode('|', $v) : json_encode($v, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($v)) {
                $out[$key] = $v ? 1 : 0;
            } else {
                $out[$key] = $v;
            }
        }

        return $out;
    }

    /** @param list<list<mixed>> $table первая строка — заголовки */
    protected function rowsFromTable(array $table): array
    {
        $table = array_values(array_filter($table, fn ($r) => collect($r)->contains(fn ($c) => $c !== null && $c !== '')));
        if (! $table) {
            return [];
        }
        $headers = array_map(fn ($h) => trim((string) $h), array_shift($table));

        return array_map(function ($r) use ($headers) {
            $row = [];
            foreach ($headers as $i => $h) {
                if ($h !== '') {
                    $row[$h] = $r[$i] ?? null;
                }
            }

            return $row;
        }, $table);
    }

    /** Строки с колонкой _entity раскладываются по сущностям. */
    protected function byEntity(array $rows, ?string $entity): array
    {
        $out = [];
        foreach ($rows as $r) {
            $e = ($r['_entity'] ?? null) ?: $entity ?? throw new \InvalidArgumentException('Укажи entity (какие это данные) или колонку _entity');
            unset($r['_entity']);
            $out[$e][] = $r;
        }

        return $out;
    }
}
