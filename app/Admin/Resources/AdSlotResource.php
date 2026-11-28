<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\AdSlot;
use App\Models\AdStat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A51 · Реклама → Места. Место — точка в шаблоне (@include('ads.slot', ['slot' => key])), здесь — его форматы, пропорции
 * резерва и код сети «если своих объявлений нет». Новый ключ начнёт работать, когда его поставят в шаблон.
 */
class AdSlotResource extends Resource
{
    public function key(): string
    {
        return 'ad-slots';
    }

    public function label(): string
    {
        return 'Рекламные места';
    }

    public function singular(): string
    {
        return 'место';
    }

    public function model(): string
    {
        return AdSlot::class;
    }

    public function icon(): string
    {
        return 'grid';
    }

    public function description(): ?string
    {
        return 'Где на сайте может стоять реклама. Пропорции резервируют место заранее — страница не «прыгает», когда объявление загрузится.';
    }

    public function search(): array
    {
        return ['key', 'name', 'description'];
    }

    public function defaultSort(): array
    {
        return ['sort', 'asc'];
    }

    public function canDelete(): bool
    {
        return false; // ключи стоят в шаблонах — место выключают, а не удаляют
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Место', 'sort' => 'name', 'render' => fn (AdSlot $s) => self::html('<b>'.e($s->name).'</b><span class="block faint text-xs">'.e($s->description).'</span>')],
            ['key' => 'key', 'label' => 'Ключ', 'sort' => 'key', 'render' => fn (AdSlot $s) => self::html('<span class="font-mono text-xs">'.e($s->key).'</span>')],
            ['key' => 'formats', 'label' => 'Форматы', 'render' => fn (AdSlot $s) => implode(', ', array_map(fn ($f) => mb_strtolower(AdSlot::FORMATS[$f] ?? $f), $s->formats ?: array_keys(AdSlot::FORMATS)))],
            ['key' => 'size', 'label' => 'Пропорции', 'render' => fn (AdSlot $s) => $s->width.'×'.$s->height.($s->m_width ? ' · тел. '.$s->m_width.'×'.$s->m_height : '')],
            ['key' => 'fallback', 'label' => 'Код сети', 'render' => fn (AdSlot $s) => self::yesNo(filled($s->fallback_code))],
            ['key' => 'is_active', 'label' => 'Включено', 'sort' => 'is_active', 'render' => fn (AdSlot $s) => self::yesNo($s->is_active)],
        ];
    }

    public function fields(?Model $model = null): array
    {
        $admin = request()->user()?->role === 'admin';

        return array_values(array_filter([
            ['name' => 'name', 'label' => 'Название', 'rules' => ['required', 'string', 'max:120']],
            ['name' => 'key', 'label' => 'Ключ в шаблоне', 'rules' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'], 'class' => 'font-mono', 'help' => 'латиница, цифры и дефис: sidebar, home-wide'],
            ['name' => 'description', 'label' => 'Где стоит', 'rules' => ['nullable', 'string', 'max:255'], 'span' => 2],
            ['name' => 'formats', 'label' => 'Какие объявления подходят', 'type' => 'checks', 'options' => AdSlot::FORMATS],
            ['name' => 'width', 'label' => 'Ширина (пропорция)', 'type' => 'number', 'rules' => ['required', 'integer', 'min:50', 'max:4000'], 'default' => 1200, 'group' => 'Пропорции резерва'],
            ['name' => 'height', 'label' => 'Высота (пропорция)', 'type' => 'number', 'rules' => ['required', 'integer', 'min:30', 'max:4000'], 'default' => 200, 'group' => 'Пропорции резерва'],
            ['name' => 'm_width', 'label' => 'Ширина на телефоне', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:50', 'max:4000'], 'group' => 'Пропорции резерва', 'help' => 'пусто — как на компьютере'],
            ['name' => 'm_height', 'label' => 'Высота на телефоне', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:30', 'max:4000'], 'group' => 'Пропорции резерва'],
            $admin ? ['name' => 'fallback_code', 'label' => 'Код рекламной сети, если своих объявлений нет', 'type' => 'textarea', 'class' => 'font-mono text-xs', 'rules' => ['nullable', 'string', 'max:10000'], 'group' => 'Запасной вариант',
                'help' => 'Например, блок AdSense (<ins class="adsbygoogle"…>). Загружается, только когда место подъезжает к экрану; пустой ответ сети скрывает место. Скрипт AdSense подключите в «Коды и счётчики».'] : null,
            ['name' => 'is_active', 'label' => 'Включено', 'type' => 'bool', 'default' => true, 'group' => 'Запасной вариант'],
            ['name' => 'sort', 'label' => 'Порядок', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0', 'max:65000'], 'default' => 100, 'group' => 'Запасной вариант'],
        ]));
    }

    public function saving(Model $m, array $data, Request $request): void
    {
        if (AdSlot::query()->where('key', $m->key)->whereKeyNot($m->id)->exists()) {
            throw ValidationException::withMessages(['key' => 'Место с таким ключом уже есть.']);
        }
        if (! $m->m_width || ! $m->m_height) {
            $m->m_width = $m->m_height = null;
        }
    }

    public function editAside(Model $m): ?string
    {
        $stats = AdStat::query()->where('slot', $m->key)->where('date', '>=', today()->subDays(29))
            ->selectRaw('sum(impressions) as v, sum(clicks) as c')->first();

        return view('admin.ads._slot-aside', ['slot' => $m, 'views' => (int) $stats?->v, 'clicks' => (int) $stats?->c])->render();
    }

    public function title(Model $m): string
    {
        return $m->name;
    }
}
