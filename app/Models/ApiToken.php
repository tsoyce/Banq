<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A23 · Токен API. Хранится только sha256; открытый токен возвращает issue() один раз. */
class ApiToken extends Model
{
    // A39 · delete — строки с _delete и DELETE /api/v1/{entity} (ставки не удаляются, а закрываются)
    public const ABILITIES = ['read' => 'Чтение: схема и экспорт', 'write' => 'Запись: импорт данных', 'delete' => 'Удаление записей'];

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected $casts = ['abilities' => 'array', 'last_used_at' => 'datetime', 'expires_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array{0: self, 1: string} модель и открытый токен (показать один раз) */
    public static function issue(string $name, array $abilities, ?int $userId = null, ?\DateTimeInterface $expiresAt = null): array
    {
        $plain = 'banq_'.Str::random(40);
        $token = static::create([
            'name' => $name, 'user_id' => $userId, 'token_hash' => hash('sha256', $plain), 'prefix' => substr($plain, 0, 10),
            'abilities' => array_values(array_intersect(array_keys(self::ABILITIES), $abilities)) ?: ['read'], 'expires_at' => $expiresAt,
        ]);

        return [$token, $plain];
    }

    public static function findPlain(?string $plain): ?self
    {
        if (! $plain) {
            return null;
        }
        $t = static::query()->where('token_hash', hash('sha256', $plain))->first();

        return $t && (! $t->expires_at || $t->expires_at->isFuture()) ? $t : null;
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities ?? [], true);
    }
}
