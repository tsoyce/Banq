<?php

namespace App\Services\Exchange\Formats;

/**
 * A22 · Формат файла обмена. Пакет (bundle) — ['entity' => [строки…]].
 * Плоские форматы (CSV) несут одну сущность — её передают явно или берут из колонки _entity.
 */
interface Format
{
    public function key(): string;

    public function extension(): string;

    public function mime(): string;

    /** @return array<string, list<array>> */
    public function read(string $content, ?string $entity = null): array;

    /** @param array<string, list<array>> $bundle */
    public function write(array $bundle): string;
}
