<?php

namespace App\Console\Commands;

use App\Services\Exchange\EntityRegistry;
use Illuminate\Console\Command;

/** A22 · php artisan banq:schema — описание формата обмена (JSON) для людей и ИИ. */
class SchemaCommand extends Command
{
    protected $signature = 'banq:schema {entity? : одна сущность}';

    protected $description = 'Схема формата обмена данными: сущности, поля, типы, примеры';

    public function handle(EntityRegistry $registry): int
    {
        $schema = $this->argument('entity') ? $registry->get($this->argument('entity'))->schema() : $registry->schema();
        $this->line(json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
