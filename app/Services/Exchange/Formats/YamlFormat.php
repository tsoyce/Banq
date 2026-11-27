<?php

namespace App\Services\Exchange\Formats;

use InvalidArgumentException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * A39 · YAML: тот же пакет, что JSON (banks: [...]), удобно писать руками и в промптах агентов.
 * Разбор — symfony/yaml без объектов и PHP-тегов.
 */
class YamlFormat implements Format
{
    public function key(): string
    {
        return 'yaml';
    }

    public function extension(): string
    {
        return 'yaml';
    }

    public function mime(): string
    {
        return 'application/yaml';
    }

    public function read(string $content, ?string $entity = null): array
    {
        try {
            // даты — объектами (иначе 2026-11-27 станет числом-таймстампом), затем обратно в строки
            $data = self::dates(Yaml::parse(preg_replace('/^\xEF\xBB\xBF/', '', $content), Yaml::PARSE_DATETIME));
        } catch (ParseException $e) {
            throw new InvalidArgumentException('Это не YAML: '.$e->getMessage());
        }
        if (! is_array($data)) {
            throw new InvalidArgumentException('YAML: ожидался пакет «сущность: [строки]» или список строк');
        }
        if (array_is_list($data)) {
            return [$entity ?? throw new InvalidArgumentException('Список строк без entity: укажи, какие это данные') => $data];
        }
        if (isset($data['rows']) && is_array($data['rows'])) {
            return [($data['entity'] ?? $entity) ?? throw new InvalidArgumentException('Укажи entity — какие это данные') => $data['rows']];
        }
        unset($data['meta'], $data['version'], $data['exported_at']);

        return array_filter($data, fn ($rows) => is_array($rows));
    }

    private static function dates(mixed $v): mixed
    {
        return match (true) {
            $v instanceof \DateTimeInterface => $v->format('H:i:s') === '00:00:00' ? $v->format('Y-m-d') : $v->format(DATE_ATOM),
            is_array($v) => array_map(self::dates(...), $v),
            default => $v,
        };
    }

    public function write(array $bundle): string
    {
        return Yaml::dump($bundle, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
    }
}
