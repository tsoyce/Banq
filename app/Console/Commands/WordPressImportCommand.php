<?php

namespace App\Console\Commands;

use App\Services\WordPress\WpImporter;
use App\Services\WordPress\WxrReader;
use Illuminate\Console\Command;

/**
 * A43 · php artisan banq:wp-import --url=https://tsoy.in [--dry-run] [--file=export.xml|snapshot.json] [--update] [--no-external] [--author=1] [--mirror=https://old.tsoy.in] [--json]
 * A52 · php artisan banq:wp-import --fill-missing --mirror=https://old.tsoy.in — пропавшие картинки с запасной копии.
 * Пробный прогон (--dry-run) показывает план и находки проверки, ничего не записывая. Без него — перенос целиком.
 */
class WordPressImportCommand extends Command
{
    protected $signature = 'banq:wp-import
        {--url= : адрес сайта WordPress (REST API /wp-json)}
        {--file= : вместо сети — файл экспорта WXR (.xml) или JSON-снимок}
        {--dry-run : только план и проверка безопасности}
        {--resume : продолжить прерванный перенос по сохранённому плану}
        {--update : перезаписать уже перенесённые записи}
        {--no-external : не скачивать картинки со сторонних сайтов}
        {--author= : id пользователя-автора для всех записей}
        {--mirror= : запасная копия сайта для файлов, которых нет на основном (https://old.tsoy.in) — A52}
        {--fill-missing : только дотянуть пропавшие картинки с --mirror в уже перенесённые записи — A52}
        {--json : отчёт в JSON}';

    protected $description = 'Перенос публикаций и страниц с WordPress (картинки, рубрики, теги, редиректы)';

    public function handle(WpImporter $importer, WxrReader $wxr): int
    {
        try {
            if ($this->option('fill-missing')) { // A52
                if (! $this->option('mirror')) {
                    $this->error('Нужен --mirror=https://old.tsoy.in');

                    return self::FAILURE;
                }
                do {
                    $p = $importer->fillMissing($this->option('mirror'), 30);
                    $this->line("Записей {$p['done']} из {$p['total']}, картинок возвращено {$p['fixed']}");
                } while (! $p['finished']);
                foreach ($p['still_missing'] as $u) {
                    $this->line('· нет и на копии: '.$u);
                }

                return self::SUCCESS;
            }
            if (! $this->option('resume')) {
                $snapshot = null;
                if ($file = $this->option('file')) {
                    $raw = @file_get_contents($file);
                    if ($raw === false) {
                        $this->error("Не удалось прочитать {$file}");

                        return self::FAILURE;
                    }
                    $snapshot = str_starts_with(ltrim($raw), '<') ? $wxr->read($raw) : json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
                }
                $state = $importer->scan($this->option('url'), $snapshot, [
                    'update' => (bool) $this->option('update'), 'external_images' => ! $this->option('no-external'),
                    'author_id' => $this->option('author') ? (int) $this->option('author') : null,
                    'mirror' => $this->option('mirror') ?: null,
                ]);
                $this->summary($state['plan']);
                if ($this->option('dry-run')) {
                    $this->option('json') && $this->line(json_encode($importer->report(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    $this->info('Пробный прогон: в базу ничего не записано. Перенос: php artisan banq:wp-import --resume');

                    return self::SUCCESS;
                }
            }

            $bar = null;
            do {
                $p = $importer->step(30);
                $bar ??= $this->output->createProgressBar(max(1, $p['total']));
                $bar->setProgress($p['done']);
            } while (! $p['finished']);
            $bar?->finish();
            $this->newLine(2);

            $r = $importer->report();
            if ($this->option('json')) {
                $this->line(json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                return self::SUCCESS;
            }
            $c = $r['counts'];
            $this->info("Готово: создано {$c['created']}, обновлено {$c['updated']}, оставлено как было {$c['kept']}; файлов {$c['media_ok']}, отклонено {$c['media_rejected']}; редиректов {$c['redirects']}.");
            foreach ($r['media_rejected'] as $m) {
                $this->line(($m['danger'] ? '<error>!</error> ' : '· ').$m['url'].' — '.$m['reason']);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function summary(array $plan): void
    {
        $c = $plan['counts'];
        $this->info("План: публикаций {$c['posts']}, страниц {$c['pages']}, пропущено {$c['skipped']} (уже перенесено: {$c['update']}); рубрик {$c['categories']}, тегов {$c['tags']}, картинок в текстах {$c['images_in_text']}, файлов на сайте {$c['media_total']}.");
        $this->line("Проверка: опасных находок {$c['danger']}, предупреждений {$c['warn']}. Связано с банками/МПС: {$c['linked']}. Комментариев на WordPress: {$c['comments']} (не переносятся).");
        $rows = collect($plan['items'])->map(fn ($i) => [$i['type'] === 'page' ? 'стр.' : 'пост', mb_strimwidth($i['title'], 0, 50, '…'), $i['skip'] ? '— '.$i['skip'] : $i['new_url'],
            collect($i['findings'])->where('level', 'danger')->count() ?: ''])->all();
        $this->table(['', 'Заголовок', 'Новый адрес', 'опасн.'], $rows);
    }
}
