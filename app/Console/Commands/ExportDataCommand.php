<?php

namespace App\Console\Commands;

use App\Services\Exchange\DataExchange;
use Illuminate\Console\Command;

/** A22 · php artisan banq:export banks deposit_rates --format=json --country=KZ --current --output=storage/app/export.json */
class ExportDataCommand extends Command
{
    protected $signature = 'banq:export
        {entities?* : какие сущности (по умолчанию — все)}
        {--format=json : json | csv | xlsx}
        {--country= : только страна (код ISO)}
        {--bank=* : только банки (коды)}
        {--current : только действующие ставки}
        {--output= : файл (по умолчанию — вывод в консоль)}';

    protected $description = 'Экспорт данных в формате обмена (тот же формат принимает banq:import)';

    public function handle(DataExchange $exchange): int
    {
        try {
            $bundle = $exchange->export($this->argument('entities'), [
                'country' => $this->option('country'), 'bank' => $this->option('bank') ?: null, 'current' => (bool) $this->option('current'),
            ]);
            $out = $exchange->render($bundle, (string) $this->option('format'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($path = $this->option('output')) {
            file_put_contents($path, $out);
            $this->info('Сохранено: '.$path.' ('.collect($bundle)->map(fn ($r, $k) => $k.': '.count($r))->implode(', ').')');
        } else {
            $this->getOutput()->write($out);
        }

        return self::SUCCESS;
    }
}
