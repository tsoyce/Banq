<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\PostCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** A37 · Рубрики блога: название, адрес /blog/c/{slug}, цвет метки, иконка, порядок. */
class PostCategoryResource extends Resource
{
    public function key(): string
    {
        return 'post-categories';
    }

    public function label(): string
    {
        return 'Рубрики';
    }

    public function singular(): string
    {
        return 'рубрику';
    }

    public function model(): string
    {
        return PostCategory::class;
    }

    public function icon(): string
    {
        return 'layers';
    }

    public function exchangeEntity(): ?string
    {
        return 'post_categories';
    }

    public function query(): Builder
    {
        return PostCategory::query()->withCount('posts');
    }

    public function defaultSort(): array
    {
        return ['sort', 'asc'];
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Рубрика', 'sort' => 'name', 'render' => fn (PostCategory $c) => self::html('<span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full" style="background:'.e($c->colorOrDefault()).'"></span><b>'.e($c->name).'</b></span><span class="faint text-xs">'.e($c->description).'</span>')],
            ['key' => 'slug', 'label' => 'Адрес', 'sort' => 'slug', 'render' => fn (PostCategory $c) => self::html('<span class="mono text-xs">/blog/c/'.e($c->slug).'</span>')],
            ['key' => 'posts', 'label' => 'Публикаций', 'sort' => 'posts_count', 'render' => fn (PostCategory $c) => self::html('<span class="tabular">'.$c->posts_count.'</span>')],
            ['key' => 'sort', 'label' => 'Порядок', 'sort' => 'sort'],
            ['key' => 'is_active', 'label' => 'На сайте', 'sort' => 'is_active', 'render' => fn (PostCategory $c) => self::yesNo($c->is_active)],
        ];
    }

    public function fields(?Model $model = null): array
    {
        return [
            ['name' => 'name', 'label' => 'Название', 'rules' => ['required', 'string', 'max:80']],
            ['name' => 'slug', 'label' => 'Адрес', 'help' => 'пусто — из названия', 'rules' => ['nullable', 'alpha_dash', 'max:80', Rule::unique('post_categories')->ignore($model)]],
            ['name' => 'description', 'label' => 'Описание', 'span' => 2, 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'color', 'label' => 'Цвет метки', 'type' => 'color', 'rules' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']],
            ['name' => 'icon', 'label' => 'Иконка', 'type' => 'select', 'options' => array_combine($i = ['news', 'chart', 'star', 'info', 'quote', 'piggy', 'card', 'bank', 'globe', 'loan', 'shield', 'spark', 'award', 'gift', 'coins', 'trend'], $i)],
            ['name' => 'sort', 'label' => 'Порядок', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0', 'max:65000'], 'default' => 500],
            ['name' => 'is_active', 'label' => 'Показывать на сайте', 'type' => 'bool', 'default' => true],
        ];
    }

    public function saving(Model $m, array $data, Request $request): void
    {
        $m->sort ??= 500;
        $m->slug = $m->slug ?: (Str::slug($m->name, '-', 'ru') ?: 'rubric-'.Str::random(4));
    }

    public function publicUrl(Model $m): ?string
    {
        return $m->is_active ? route('blog.category', $m) : null;
    }

    public function summary(): array
    {
        return ['Рубрик' => PostCategory::count()];
    }
}
