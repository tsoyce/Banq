<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/** A24 · Пользователи и роли (только для admin). Личный кабинет — отдельный модуль профиля. */
class UserResource extends Resource
{
    public const ROLES = ['admin' => 'Администратор', 'editor' => 'Редактор', 'user' => 'Пользователь'];

    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'Пользователи';
    }

    public function singular(): string
    {
        return 'пользователя';
    }

    public function model(): string
    {
        return User::class;
    }

    public function icon(): string
    {
        return 'users';
    }

    public function roles(): array
    {
        return ['admin'];
    }

    public function query(): Builder
    {
        return User::query()->withCount('reviews');
    }

    public function search(): array
    {
        return ['name', 'email'];
    }

    public function filters(): array
    {
        return ['role' => ['label' => 'Роль', 'options' => self::ROLES, 'apply' => fn ($q, $v) => $q->where('role', $v)]];
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Имя', 'sort' => 'name', 'render' => fn (User $u) => self::html('<p class="font-semibold">'.e($u->name).'</p><p class="faint text-xs">'.e($u->email).'</p>')],
            ['key' => 'role', 'label' => 'Роль', 'sort' => 'role', 'render' => fn (User $u) => self::html('<span class="chip">'.e(self::ROLES[$u->role] ?? $u->role).'</span>')],
            ['key' => 'reviews_count', 'label' => 'Отзывов', 'sort' => 'reviews_count', 'class' => 'tabular'],
            ['key' => 'created_at', 'label' => 'С нами с', 'sort' => 'created_at', 'render' => fn (User $u) => $u->created_at?->format('d.m.Y')],
        ];
    }

    public function fields(?Model $model = null): array
    {
        return [
            ['name' => 'name', 'label' => 'Имя', 'rules' => ['required', 'string', 'max:120']],
            ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'rules' => ['required', 'email', 'max:191', Rule::unique('users')->ignore($model)]],
            ['name' => 'role', 'label' => 'Роль', 'type' => 'select', 'options' => self::ROLES, 'rules' => ['required'], 'default' => 'user'],
            ['name' => 'password', 'label' => $model ? 'Новый пароль' : 'Пароль', 'type' => 'password', 'rules' => [$model ? 'nullable' : 'required', 'string', 'min:8', 'max:191'], 'help' => $model ? 'пусто — не менять' : null],
        ];
    }

    public function saving(Model $m, array $data, Request $request): void
    {
        // пароль хешируется кастом 'hashed' модели User
        // себя нельзя лишить прав админа — иначе можно запереть админку
        if ($m->is($request->user()) && $m->role !== 'admin') {
            $m->role = 'admin';
        }
    }

    public function bulkActions(): array
    {
        $role = fn (string $r) => fn (Collection $m, Request $req) => 'Роль «'.self::ROLES[$r].'»: '.$m->reject(fn ($u) => $u->is($req->user()))->each->update(['role' => $r])->count();

        return [
            'editor' => ['label' => 'Сделать редакторами', 'run' => $role('editor')],
            'user' => ['label' => 'Сделать пользователями', 'run' => $role('user')],
            'delete' => ['label' => 'Удалить', 'danger' => true, 'confirm' => 'Удалить пользователей вместе с их отзывами?',
                'run' => fn (Collection $m, Request $req) => 'Удалено: '.$m->reject(fn ($u) => $u->is($req->user()))->each->delete()->count()],
        ];
    }

    public function summary(): array
    {
        return ['Всего' => User::count(), 'Админы и редакторы' => User::whereIn('role', ['admin', 'editor'])->count(), 'Новых за 30 дней' => User::where('created_at', '>=', now()->subDays(30))->count()];
    }
}
