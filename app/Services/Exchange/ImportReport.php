<?php

namespace App\Services\Exchange;

/**
 * A22 · Отчёт импорта: счётчики по сущностям и построчный журнал (что создано, что поменялось — дифф).
 * Отдаётся в админку (предпросмотр), в CLI и в API как JSON (toArray).
 */
final class ImportReport
{
    // A39 · deleted — строки с _delete (удалено / ставка закрыта)
    public const ACTIONS = ['created', 'updated', 'deleted', 'unchanged', 'skipped', 'error'];

    /** Сколько строк журнала хранить (счётчики — всегда полные). */
    public const MAX_ROWS = 2000;

    /** @var array<string, array<string, int>> */
    public array $stats = [];

    /** @var list<array{entity: string, row: int, action: string, key: string, changes?: array, message?: string}> */
    public array $rows = [];

    /** @var list<string> */
    public array $warnings = [];

    public bool $truncated = false;

    /** id записи в imports (после настоящего импорта). */
    public ?int $importId = null;

    public function add(string $entity, int $row, string $action, string $key, array $changes = [], ?string $message = null): void
    {
        $this->stats[$entity] ??= array_fill_keys(self::ACTIONS, 0);
        $this->stats[$entity][$action]++;

        if (count($this->rows) >= self::MAX_ROWS) {
            $this->truncated = true;

            return;
        }
        $this->rows[] = array_filter(compact('entity', 'row', 'action', 'key', 'changes', 'message'), fn ($v) => $v !== [] && $v !== null);
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function total(?string $action = null): int
    {
        return array_sum(array_map(fn ($s) => $action ? $s[$action] : array_sum($s), $this->stats));
    }

    public function errors(): array
    {
        return array_values(array_filter($this->rows, fn ($r) => $r['action'] === 'error'));
    }

    public function hasChanges(): bool
    {
        return $this->total('created') + $this->total('updated') + $this->total('deleted') > 0;
    }

    public function toArray(): array
    {
        return [
            'totals' => array_combine(self::ACTIONS, array_map(fn ($a) => $this->total($a), self::ACTIONS)),
            'stats' => $this->stats,
            'warnings' => $this->warnings,
            'rows' => $this->rows,
            'truncated' => $this->truncated,
            'import_id' => $this->importId,
        ];
    }
}
