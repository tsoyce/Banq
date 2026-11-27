<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** A37 · Комментарии к публикациям: модерация (показать / скрыть), правка текста, удаление. */
class CommentResource extends Resource
{
    public function key(): string
    {
        return 'comments';
    }

    public function label(): string
    {
        return 'Комментарии';
    }

    public function singular(): string
    {
        return 'комментарий';
    }

    public function model(): string
    {
        return Comment::class;
    }

    public function icon(): string
    {
        return 'quote';
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function query(): Builder
    {
        return Comment::query()->with(['post', 'user']);
    }

    public function search(): array
    {
        return ['body', 'user.name', 'user.email', 'post.title'];
    }

    public function filters(): array
    {
        return ['status' => ['label' => 'Статус', 'options' => Comment::STATUSES, 'apply' => fn ($q, $v) => $q->where('status', $v)]];
    }

    public function columns(): array
    {
        return [
            ['key' => 'body', 'label' => 'Комментарий', 'render' => fn (Comment $c) => self::html('<span class="block max-w-[34rem]">'.e(Str::limit($c->body, 160)).'</span><span class="faint text-xs">'.e($c->user?->name).' · к «'.e(Str::limit($c->post?->title, 60)).'»</span>')],
            ['key' => 'status', 'label' => 'Статус', 'sort' => 'status', 'render' => fn (Comment $c) => self::html('<span class="chip status-'.['approved' => 'approved', 'pending' => 'pending', 'hidden' => 'rejected'][$c->status].'"><span class="status-dot"></span>'.e(Comment::STATUSES[$c->status] ?? $c->status).'</span>')],
            ['key' => 'created_at', 'label' => 'Когда', 'sort' => 'created_at', 'render' => fn (Comment $c) => self::html('<span class="text-sm whitespace-nowrap">'.e($c->created_at?->diffForHumans()).'</span>')],
        ];
    }

    public function fields(?Model $model = null): array
    {
        return [
            ['name' => 'body', 'label' => 'Текст', 'type' => 'textarea', 'rules' => ['required', 'string', 'max:3000']],
            ['name' => 'status', 'label' => 'Статус', 'type' => 'select', 'options' => Comment::STATUSES, 'rules' => ['required']],
        ];
    }

    public function title(Model $m): string
    {
        return 'Комментарий #'.$m->id;
    }

    public function publicUrl(Model $m): ?string
    {
        return $m->post ? route('blog.show', $m->post).'#comment-'.$m->id : null;
    }

    public function bulkActions(): array
    {
        return [
            'approve' => ['label' => 'Показать', 'run' => fn (Collection $m) => 'Показано: '.$m->each->update(['status' => 'approved'])->count()],
            'hide' => ['label' => 'Скрыть', 'run' => fn (Collection $m) => 'Скрыто: '.$m->each->update(['status' => 'hidden'])->count()],
            ...parent::bulkActions(),
        ];
    }

    public function summary(): array
    {
        return ['Всего' => Comment::count(), 'На проверке' => Comment::where('status', 'pending')->count(), 'Скрыто' => Comment::where('status', 'hidden')->count()];
    }
}
