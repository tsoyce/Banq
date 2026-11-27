<?php

namespace App\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A24 · Ресурс админки: одно описание → список с поиском, фильтрами, сортировкой, пагинацией,
 * массовыми действиями, экспортом выбранного и форма создания/правки.
 * Контроллер — App\Http\Controllers\Admin\ResourceController, шаблоны — resources/views/admin/resource/*.
 * Новый раздел (карты, кредиты) = новый класс в App\Admin\Resources + строка в ResourceRegistry.
 *
 * Поле формы: ['name', 'label', 'type' => text|textarea|number|decimal|select|bool|color|date|list|json|password|email|url,
 *              'rules' => [...], 'options' => [v => label] | Closure, 'help', 'span' => 2, 'group' => 'Основное', 'placeholder'].
 * Имя с точкой (regulator.short) пишется внутрь JSON-поля модели.
 * Колонка: ['key', 'label', 'sort' => 'db_column'|null, 'render' => fn (Model) => string|HtmlString, 'class'].
 */
abstract class Resource
{
    /** Сегмент URL: /admin/{key}. */
    abstract public function key(): string;

    abstract public function label(): string;

    /** Подпись одной записи: «банк», «вклад». */
    abstract public function singular(): string;

    /** @return class-string<Model> */
    abstract public function model(): string;

    abstract public function columns(): array;

    abstract public function fields(?Model $model = null): array;

    /** Иконка из partials.icon. */
    public function icon(): string
    {
        return 'grid';
    }

    public function description(): ?string
    {
        return null;
    }

    /** Базовый запрос списка (жадные загрузки, счётчики). */
    public function query(): Builder
    {
        return ($this->model())::query();
    }

    /** Колонки поиска (LIKE) или Closure($q, $term). */
    public function search(): array|\Closure
    {
        return ['name'];
    }

    /** Фильтры: name => ['label', 'options' => [v => label], 'apply' => fn (Builder, $value)]. */
    public function filters(): array
    {
        return [];
    }

    public function defaultSort(): array
    {
        return ['id', 'desc'];
    }

    /**
     * Массовые действия: key => ['label', 'run' => fn (Collection $models, Request) => string (сообщение), 'danger' => bool, 'confirm' => текст].
     */
    public function bulkActions(): array
    {
        return $this->canDelete() ? ['delete' => [
            'label' => 'Удалить', 'danger' => true, 'confirm' => 'Удалить выбранное безвозвратно?',
            'run' => fn (Collection $models) => 'Удалено: '.$models->each->delete()->count(),
        ]] : [];
    }

    /** Ключ сущности обмена данными (App\Services\Exchange) — даёт «Экспорт выбранного» JSON/CSV/XLSX. */
    public function exchangeEntity(): ?string
    {
        return null;
    }

    public function canCreate(): bool
    {
        return true;
    }

    public function canEdit(Model $m): bool
    {
        return true;
    }

    public function canDelete(): bool
    {
        return true;
    }

    /** Кто видит ресурс: роли. */
    public function roles(): array
    {
        return ['admin', 'editor'];
    }

    public function title(Model $m): string
    {
        return (string) ($m->getAttribute('name') ?? '#'.$m->getKey());
    }

    /** Дополнительный блок под формой правки (например, ставки вклада). */
    public function editAside(Model $m): ?string
    {
        return null;
    }

    /** Ссылка «на сайте». */
    public function publicUrl(Model $m): ?string
    {
        return null;
    }

    /** Значения по умолчанию для новой записи. */
    public function defaults(Request $request): array
    {
        return [];
    }

    /** Перед сохранением: можно поправить данные / модель. */
    public function saving(Model $m, array $data, Request $request): void {}

    /** Сводка над списком: [подпись => число]. */
    public function summary(): array
    {
        return [];
    }

    // ── Общая механика ──────────────────────────────────────

    /** Применить поиск, фильтры и сортировку запроса. */
    public function applyRequest(Builder $q, Request $request): Builder
    {
        $term = trim((string) $request->query('q'));
        if ($term !== '') {
            $search = $this->search();
            if ($search instanceof \Closure) {
                $search($q, $term);
            } else {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
                $q->where(function ($w) use ($search, $like, $term) {
                    foreach ($search as $col) {
                        str_contains($col, '.')
                            ? $w->orWhereHas(Str::beforeLast($col, '.'), fn ($r) => $r->where(Str::afterLast($col, '.'), 'like', $like))
                            : $w->orWhere($col, 'like', $like);
                    }
                    if (ctype_digit($term)) {
                        $w->orWhere($w->getModel()->getQualifiedKeyName(), (int) $term);
                    }
                });
            }
        }

        foreach ($this->filters() as $name => $f) {
            $v = $request->query($name);
            if ($v !== null && $v !== '' && (! isset($f['options']) || array_key_exists($v, $this->options($f['options'])))) {
                ($f['apply'])($q, $v);
            }
        }

        [$col, $dir] = $this->sort($request);
        $q->reorder()->orderBy($col, $dir);
        if ($col !== 'id') {
            $q->orderBy($q->getModel()->getQualifiedKeyName(), 'desc');
        }

        return $q;
    }

    /** [колонка, направление] — только из разрешённых колонок. */
    public function sort(Request $request): array
    {
        $allowed = array_filter(array_map(fn ($c) => $c['sort'] ?? null, $this->columns()));
        $col = (string) $request->query('sort');
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        return in_array($col, $allowed, true) ? [$col, $dir] : $this->defaultSort();
    }

    public function options(array|\Closure $options): array
    {
        return $options instanceof \Closure ? $options() : $options;
    }

    /** Правила валидации из полей формы. */
    public function rules(?Model $m): array
    {
        $rules = [];
        foreach ($this->fields($m) as $f) {
            $r = $f['rules'] ?? ['nullable'];
            $type = $f['type'] ?? 'text';
            if ($type === 'select' && isset($f['options']) && ! collect($r)->contains(fn ($x) => is_string($x) && str_starts_with($x, 'in:'))) {
                $r[] = Rule::in(array_map('strval', array_keys($this->options($f['options']))));
            }
            if ($type === 'json') {
                $r[] = 'json';
            }
            $rules[$f['name']] = $r;
        }

        return $rules;
    }

    /** Значение поля для формы (с old()). */
    public function value(?Model $m, array $f, array $defaults = []): mixed
    {
        $v = $m ? data_get($m, $f['name']) : ($defaults[$f['name']] ?? ($f['default'] ?? null));
        $v = $v instanceof \BackedEnum ? $v->value : $v;
        $v = $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;

        return match ($f['type'] ?? 'text') {
            'list' => is_array($v) ? implode("\n", $v) : $v,
            'json' => $v === null ? '' : (is_string($v) ? $v : json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            'password' => '',
            default => $v,
        };
    }

    /** Заполнить модель проверенными данными формы. */
    public function fill(Model $m, array $data): void
    {
        foreach ($this->fields($m) as $f) {
            $name = $f['name'];
            if (! array_key_exists($name, $data) && ($f['type'] ?? '') !== 'bool' && ! str_contains($name, '.')) {
                continue;
            }
            $v = data_get($data, $name);
            $v = match ($f['type'] ?? 'text') {
                'bool' => (bool) $v,
                'list' => $v === null ? null : array_values(array_filter(array_map('trim', preg_split('/[\n,|]+/u', (string) $v)))),
                'json' => $v === null || $v === '' ? null : json_decode($v, true),
                'password' => $v,
                default => $v === '' ? null : $v,
            };
            if (($f['type'] ?? '') === 'password' && ($v === null || $v === '')) {
                continue;
            }
            if (($f['type'] ?? '') === 'bool' && str_contains($name, '.') === false && ! array_key_exists($name, $data)) {
                $v = false;
            }
            if (str_contains($name, '.')) {
                $attr = Str::before($name, '.');
                $json = (array) ($m->getAttribute($attr) ?? []);
                data_set($json, Str::after($name, '.'), $v === '' ? null : $v);
                $m->setAttribute($attr, self::prune($json) ?: null);
            } else {
                $m->setAttribute($name, $v);
            }
        }
    }

    private static function prune(array $a): array
    {
        foreach ($a as $k => $v) {
            if (is_array($v)) {
                $a[$k] = self::prune($v);
            }
            if ($a[$k] === null || $a[$k] === '' || $a[$k] === []) {
                unset($a[$k]);
            }
        }

        return $a;
    }

    /** Группы полей для формы: группа → поля. */
    public function groups(?Model $m): array
    {
        return collect($this->fields($m))->groupBy(fn ($f) => $f['group'] ?? 'Основное')->map->all()->all();
    }

    // Помощники для колонок
    protected static function html(string $html): HtmlString
    {
        return new HtmlString($html);
    }

    protected static function yesNo(?bool $v): HtmlString
    {
        return new HtmlString($v ? '<span class="chip !text-[var(--ok)]">да</span>' : '<span class="chip faint">нет</span>');
    }
}
