<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/** A37 · Тег публикации. Создаётся на лету из списка тегов в редакторе или импорте. */
class Tag extends Model
{
    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    /** Найти или создать теги по названиям; вернуть их id. */
    public static function idsFor(iterable $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $name = trim(preg_replace('/\s+/u', ' ', (string) $name), " \t#");
            if ($name === '' || mb_strlen($name) > 80) {
                continue;
            }
            $slug = Str::slug($name, '-', 'ru') ?: 'tag-'.substr(md5($name), 0, 8);
            $ids[] = static::query()->firstOrCreate(['slug' => $slug], ['name' => $name])->id;
        }

        return array_values(array_unique($ids));
    }
}
