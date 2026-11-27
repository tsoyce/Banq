<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use Illuminate\Console\Command;

/** A23 · php artisan banq:token "Claude" --write [--days=30] — выпустить токен API (показывается один раз). */
class IssueTokenCommand extends Command
{
    protected $signature = 'banq:token {name : кому/зачем} {--write : разрешить импорт} {--days= : срок жизни в днях}';

    protected $description = 'Выпустить токен API обмена данными';

    public function handle(): int
    {
        [$token, $plain] = ApiToken::issue((string) $this->argument('name'), $this->option('write') ? ['read', 'write'] : ['read'],
            expiresAt: $this->option('days') ? now()->addDays((int) $this->option('days')) : null);
        $this->info("Токен «{$token->name}» (".implode(', ', $token->abilities).'):');
        $this->line($plain);
        $this->comment('Сохрани его сейчас — в базе только хеш. Отозвать: /admin/exchange/tokens');

        return self::SUCCESS;
    }
}
