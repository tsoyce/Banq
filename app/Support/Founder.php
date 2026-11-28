<?php

namespace App\Support;

use App\Models\User;

/**
 * A46 · Основатель и автор TSOY.IN — хозяин страницы /author.
 * Настройка author.user_id (админка → Сайт → «Автор и соцсети»), по умолчанию — первый админ.
 * Публикации, перенесённые с WordPress (A43), записаны на него же.
 */
class Founder
{
    private ?User $user = null;

    private bool $resolved = false;

    public function user(): ?User
    {
        if ($this->resolved) {
            return $this->user;
        }
        $this->resolved = true;
        $id = (int) settings('author.user_id');

        try {
            return $this->user = ($id ? User::query()->find($id) : null)
                ?? User::query()->where('role', 'admin')->orderBy('id')->first();
        } catch (\Throwable) {
            return null; // до установки нет базы
        }
    }

    public function is(?User $user): bool
    {
        return $user !== null && $this->user()?->is($user) === true;
    }

    /** Адрес автора: страница /author для основателя, публичный профиль — для остальных. */
    public function urlFor(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return $this->is($user) ? route('author') : $user->profileUrl();
    }
}
