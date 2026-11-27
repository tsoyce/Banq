<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A37 · Рубрика блога: «Новости», «Аналитика», «Обзоры»… Цвет и иконка — для меток в ленте. */
class PostCategory extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'sort' => 'integer'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'category_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort')->orderBy('name');
    }

    public function colorOrDefault(): string
    {
        return $this->color ?: '#7b5cff';
    }
}
