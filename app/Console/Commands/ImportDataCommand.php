<?php

namespace App\Console\Commands;

use App\Services\Exchange\DataExchange;
use App\Services\Exchange\ImportOptions;
use Illuminate\Console\Command;

/** A22 · php artisan banq:import data.json [--dry-run] [--entity=banks] [--format=csv] [--mode=upsert] [--country=KZ] */
class ImportDataCommand extends Command
{
    protected $signature = 'banq:import
        {file : путь к файлу или «-» для stdin}
        {--entity= : какие это данные, если файл плоский (banks, deposit_rates…)}
        {--format= : json | ndjson | xml | yaml | csv | tsv | xlsx (по умолчанию — по расширению/содержимому)}
        {--mode=upsert : upsert | create | update}
        {--country= : страна по умолчанию для строк без country (код ISO)}
        {--dry-run : только проверить и показать, что изменится}
        {--allow-delete : A39 — выполнять строки с _delete}
        {--json : вывести отчёт JSON}';

    protected $description = 'Импорт данных в любом формате обмена (схема — banq:schema)';

    public function handle(DataExchange $exchange): int
    {
        $file = (string) $this->argument('file');
        $content = $file === '-' ? stream_get_contents(STDIN) : @file_get_contents($file);
        if ($content === false || $content === '') {
            $this->error("Не удалось прочитать {$file}");

            return self::FAILURE;
        }

        try {
            $format = $this->option('format') ?: $exchange->detect($content, $file === '-' ? null : $file);
            $bundle = $exchange->parse($content, $format, $this->option('entity') ?: null, $file);
            $report = $exchange->import($bundle, new ImportOptions(
                mode: (string) $this->option('mode'), dryRun: (bool) $this->option('dry-run'), country: $this->option('country') ?: null,
                source: 'cli', filename: basename($file), format: $format, allowDelete: (bool) $this->option('allow-delete'),
            ));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $report->total('error') ? self::FAILURE : self::SUCCESS;
        }

        $this->table(['Сущность', 'новых', 'обновлено', 'удалено', 'без изменений', 'пропущено', 'ошибок'],
            collect($report->stats)->map(fn ($s, $e) => [$e, $s['created'], $s['updated'], $s['deleted'], $s['unchanged'], $s['skipped'], $s['error']])->values()->all());
        foreach ($report->rows as $r) {
            if (in_array($r['action'], ['error', 'updated', 'deleted', 'skipped'], true)) {
                $this->line(sprintf('  %-8s %s #%d %s%s', $r['action'], $r['entity'], $r['row'], $r['key'], isset($r['message']) ? ' — '.$r['message'] : ''));
            }
        }
        $this->info($this->option('dry-run') ? 'Пробный прогон: ничего не записано.' : 'Готово, импорт #'.$report->importId);

        return $report->total('error') ? self::FAILURE : self::SUCCESS;
    }
}
