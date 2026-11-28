<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\Redirect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** A42 · Редиректы со старых адресов: вручную или после переноса с WordPress (source = wp). Счётчик переходов. */
class RedirectResource extends Resource
{
    public function key(): string
    {
        return 'redirects';
    }

    public function label(): string
    {
        return 'Редиректы';
    }

    public function singular(): string
    {
        return 'редирект';
    }

    public function model(): string
    {
        return Redirect::class;
    }

    public function icon(): string
    {
        return 'arrow';
    }

    public function description(): ?string
    {
        return 'Старый адрес → новый (301 — навсегда). Срабатывает, только если по старому адресу на сайте ничего нет.';
    }

    public function search(): array
    {
        return ['from_path', 'to_url', 'note'];
    }

    public function defaultSort(): array
    {
        return ['hits', 'desc'];
    }

    public function filters(): array
    {
        return ['source' => ['label' => 'Источник', 'options' => ['wp' => 'WordPress', 'manual' => 'Вручную'], 'apply' => fn ($q, $v) => $q->where('source', $v)]];
    }

    public function columns(): array
    {
        return [
            ['key' => 'from_path', 'label' => 'Старый адрес', 'sort' => 'from_path', 'render' => fn (Redirect $r) => self::html('<span class="font-mono text-xs break-all">'.e($r->from_path).'</span>')],
            ['key' => 'to_url', 'label' => 'Новый адрес', 'sort' => 'to_url', 'render' => fn (Redirect $r) => self::html('<a class="font-mono text-xs break-all underline underline-offset-4" href="'.e($r->target()).'" target="_blank">'.e($r->to_url).'</a>')],
            ['key' => 'status', 'label' => 'Код', 'sort' => 'status', 'class' => 'tabular'],
            ['key' => 'source', 'label' => 'Источник', 'sort' => 'source', 'render' => fn (Redirect $r) => ['wp' => 'WordPress', 'manual' => 'Вручную'][$r->source] ?? '—'],
            ['key' => 'hits', 'label' => 'Переходов', 'sort' => 'hits', 'class' => 'tabular'],
            ['key' => 'last_hit_at', 'label' => 'Последний', 'sort' => 'last_hit_at', 'render' => fn (Redirect $r) => $r->last_hit_at?->diffForHumans() ?? '—'],
        ];
    }

    public function fields(?Model $model = null): array
    {
        return [
            ['name' => 'from_path', 'label' => 'Старый адрес', 'help' => 'путь или полный адрес: /old-post/ или https://tsoy.in/old-post/', 'rules' => ['required', 'string', 'max:500']],
            ['name' => 'to_url', 'label' => 'Новый адрес', 'help' => '/blog/new-post или https://…', 'rules' => ['required', 'string', 'max:500', 'regex:#^(/|https?://)#i']],
            ['name' => 'status', 'label' => 'Код', 'type' => 'select', 'options' => [301 => '301 — навсегда', 302 => '302 — временно'], 'rules' => ['required', 'in:301,302'], 'default' => 301],
            ['name' => 'note', 'label' => 'Заметка', 'rules' => ['nullable', 'string', 'max:255']],
        ];
    }

    public function roles(): array
    {
        return ['admin', 'editor'];
    }

    public function saving(Model $m, array $data, Request $request): void
    {
        $m->source ??= 'manual';
        $from = Redirect::normalize((string) $m->from_path);
        if (Redirect::query()->where('from_hash', hash('sha256', $from))->whereKeyNot($m->id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['from_path' => 'Для этого адреса редирект уже есть.']);
        }
    }

    public function title(Model $m): string
    {
        return $m->from_path;
    }

    public function publicUrl(Model $m): ?string
    {
        return url(strtok($m->from_path, '?') ?: '/');
    }

    public function summary(): array
    {
        return ['Редиректов' => Redirect::count(), 'Переходов' => (int) Redirect::sum('hits')];
    }
}
