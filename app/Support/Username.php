<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/** A28 · Адрес публичного профиля /u/{username}: латиница, цифры, дефис; 3–40 символов. */
final class Username
{
    public const PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,38})[a-z0-9]$/';

    /** Служебные слова — не отдаём под профили. */
    public const RESERVED = ['admin', 'api', 'account', 'login', 'logout', 'register', 'install', 'banks', 'deposits', 'ratings', 'user', 'users', 'banq', 'tsoy', 'support', 'help', 'root'];

    public static function generate(string $name, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($name, '-', 'ru'), 30, '') ?: 'user';
        if (strlen($base) < 3 || in_array($base, self::RESERVED, true)) {
            $base .= '-'.random_int(10, 99);
        }
        $candidate = $base;
        $i = 2;
        while (User::where('username', $candidate)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }
}
