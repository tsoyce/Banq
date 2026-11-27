<?php

namespace App\Services\Exchange\Entities;

use App\Models\PostCategory;
use App\Services\Exchange\Entity;
use App\Services\Exchange\ImportContext;
use App\Services\Exchange\RowError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A37 · Рубрики блога: ключ — slug (или name). */
class PostCategoryEntity extends Entity
{
    public function key(): string
    {
        return 'post_categories';
    }

    public function label(): string
    {
        return 'Рубрики блога';
    }

    public function description(): string
    {
        return 'Рубрики публикаций: Новости, Аналитика, Обзоры… Ключ — slug; если его нет — name.';
    }

    public function aliases(): array
    {
        return ['categories', 'рубрики', 'рубрики блога'];
    }

    public function fields(): array
    {
        return [
            'slug' => ['string', 'example' => 'analytics', 'rules' => 'max:80|alpha_dash'],
            'name' => ['string', 'example' => 'Аналитика', 'rules' => 'max:80', 'aliases' => ['название', 'рубрика']],
            'description' => ['string', 'rules' => 'max:255', 'aliases' => ['описание']],
            'color' => ['string', 'rules' => 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['string', 'rules' => 'max:30'],
            'is_active' => ['bool'],
            'sort' => ['int'],
        ];
    }

    public function find(array $row, ImportContext $ctx): ?Model
    {
        return isset($row['slug']) ? PostCategory::query()->where('slug', $row['slug'])->first()
            : (isset($row['name']) ? PostCategory::query()->where('name', $row['name'])->first() : throw new RowError('нужен slug или name'));
    }

    public function attributes(array $row, ImportContext $ctx, ?Model $existing): array
    {
        if (! $existing) {
            $row['name'] ?? throw new RowError('для новой рубрики нужен name');
            $row['slug'] ??= Str::slug($row['name'], '-', 'ru');
        }

        return $row;
    }

    public function newModel(): Model
    {
        return new PostCategory;
    }

    public function rowLabel(array $row): string
    {
        return $row['slug'] ?? $row['name'] ?? '?';
    }

    public function exportQuery(array $filters): Builder
    {
        return PostCategory::query()->ordered();
    }

    public function toRow(Model $m): array
    {
        return $this->exportFields($m);
    }
}
