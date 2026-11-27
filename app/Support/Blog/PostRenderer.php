<?php

namespace App\Support\Blog;

use App\Models\Post;
use App\Services\Banks\BankDirectory;
use App\Services\Deposits\DepositCatalog;
use App\Services\Deposits\DepositFilters;
use Illuminate\Support\Str;

/**
 * A37 · Отрисовка тела публикации для витрины:
 *  — якоря у заголовков H2/H3 и оглавление;
 *  — живые вставки продуктов <div data-embed="…" data-slug="…"> → карточки с текущими ставками/условиями
 *    (данные берутся в момент показа, поэтому статья не устаревает при смене ставок);
 *  — таблицы в обёртке с горизонтальной прокруткой.
 */
class PostRenderer
{
    /** @return array{html: string, toc: list<array{id: string, text: string, level: int}>} */
    public function render(Post $post): array
    {
        $html = (string) $post->body;
        $toc = [];
        $used = [];

        $html = preg_replace_callback('#<h([234])(?:\s+id="([^"]*)")?>(.*?)</h\1>#su', function ($m) use (&$toc, &$used) {
            $text = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5));
            $base = $m[2] ?: (Str::slug(Str::limit($text, 60, ''), '-', 'ru') ?: 'razdel');
            $id = $base;
            for ($i = 2; isset($used[$id]); $i++) {
                $id = $base.'-'.$i;
            }
            $used[$id] = true;
            if ($m[1] !== '4' && $text !== '') {
                $toc[] = ['id' => $id, 'text' => $text, 'level' => (int) $m[1]];
            }

            return '<h'.$m[1].' id="'.$id.'">'.$m[3].'<a href="#'.$id.'" class="h-anchor" aria-label="Ссылка на раздел">#</a></h'.$m[1].'>';
        }, $html);

        $html = preg_replace_callback('#<div\s+([^>]*data-embed[^>]*)>\s*</div>#u', function ($m) {
            preg_match('/data-embed="([a-z_]+)"/', $m[1], $t);
            preg_match('/data-slug="([^"]+)"/', $m[1], $s);

            return $this->embed($t[1] ?? '', $s[1] ?? '');
        }, $html);

        $html = preg_replace(['#<table>#', '#</table>#'], ['<div class="prose-table"><table>', '</table></div>'], $html);
        // числовые ячейки («1 000 000 ₸», «16,5%») — без переноса и по правому краю
        $html = preg_replace_callback('#<(td|th)((?:\s[^>]*)?)>((?:<p>)?)([^<]{1,40})((?:</p>)?)</\1>#u', fn ($m) => preg_match('/^[\s\x{00A0}\d.,%₸$€₽¥+−–-]+$/u', html_entity_decode($m[4])) && preg_match('/\d/', $m[4])
            ? '<'.$m[1].$m[2].' class="num">'.$m[3].$m[4].$m[5].'</'.$m[1].'>' : $m[0], $html);

        return ['html' => $html, 'toc' => $toc];
    }

    /** Карточка вставки или пусто, если продукт скрыт/удалён. */
    public function embed(string $type, string $slug): string
    {
        $model = Post::resolveLink($type.':'.$slug);
        if (! $model) {
            return '';
        }

        $data = match ($type) {
            'bank' => $model->status === 'active' ? ['bank' => $model, 'stats' => app(BankDirectory::class)->for($model)] : null,
            'deposit' => $model->is_active && $model->bank?->status === 'active'
                ? ['product' => $model, 'rate' => app(DepositCatalog::class)->bestRate($model, new DepositFilters)] : null,
            'card' => $model->is_active && $model->bank?->status === 'active' ? ['card' => $model] : null,
            'package' => $model->is_active ? ['package' => $model] : null,
            'payment_system' => $model->is_active ? ['ps' => $model] : null,
            default => null,
        };

        return $data === null ? '' : view('blog.embeds.'.$type, $data)->render();
    }
}
