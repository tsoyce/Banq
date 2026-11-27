<?php

namespace App\Services\Exchange\Formats;

use InvalidArgumentException;

/**
 * A39 · NDJSON / JSON Lines: по объекту на строку, сущность — в поле _entity (или параметр entity).
 * Удобно агентам: можно писать потоком, не держа весь пакет в памяти; вложенность как в JSON.
 */
class NdjsonFormat implements Format
{
    public function key(): string
    {
        return 'ndjson';
    }

    public function extension(): string
    {
        return 'ndjson';
    }

    public function mime(): string
    {
        return 'application/x-ndjson';
    }

    public function read(string $content, ?string $entity = null): array
    {
        $out = [];
        foreach (preg_split('/\R/u', preg_replace('/^\xEF\xBB\xBF/', '', $content)) as $n => $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (! is_array($row) || array_is_list($row)) {
                throw new InvalidArgumentException('Строка '.($n + 1).': ожидался JSON-объект — '.json_last_error_msg());
            }
            $e = ($row['_entity'] ?? null) ?: $entity ?? throw new InvalidArgumentException('Строка '.($n + 1).': нет _entity — укажи, какие это данные');
            unset($row['_entity']);
            $out[$e][] = $row;
        }

        return $out;
    }

    public function write(array $bundle): string
    {
        $lines = [];
        foreach ($bundle as $entity => $rows) {
            foreach ($rows as $row) {
                $lines[] = json_encode(['_entity' => $entity] + (array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
            }
        }

        return $lines ? implode("\n", $lines)."\n" : '';
    }
}
