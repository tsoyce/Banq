<?php

namespace App\Services\Exchange\Formats;

use InvalidArgumentException;

/**
 * A22 · JSON: пакет {"banks": [...], ...}, или {"entity": "banks", "rows": [...]}, или просто массив строк (+ entity).
 * Понимает и JSON Lines (по объекту на строку).
 */
class JsonFormat implements Format
{
    public function key(): string
    {
        return 'json';
    }

    public function extension(): string
    {
        return 'json';
    }

    public function mime(): string
    {
        return 'application/json';
    }

    public function read(string $content, ?string $entity = null): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', trim($content));
        $data = json_decode($content, true);
        if (! is_array($data) && str_contains($content, "\n")) {
            // JSON Lines
            $data = array_values(array_filter(array_map(fn ($l) => json_decode($l, true), preg_split('/\R/u', $content))));
        }
        if (! is_array($data)) {
            throw new InvalidArgumentException('Это не JSON: '.json_last_error_msg());
        }
        if (isset($data['rows']) && is_array($data['rows'])) {
            return [($data['entity'] ?? $entity) ?? throw new InvalidArgumentException('Укажи entity — какие это данные') => $data['rows']];
        }
        if (array_is_list($data)) {
            return [$entity ?? throw new InvalidArgumentException('Массив строк без entity: укажи, какие это данные (banks, deposit_rates…)') => $data];
        }
        unset($data['$schema'], $data['meta'], $data['version'], $data['exported_at']);

        return array_filter($data, fn ($rows) => is_array($rows));
    }

    public function write(array $bundle): string
    {
        return json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
