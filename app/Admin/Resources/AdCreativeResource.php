<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** A51 · Реклама → Объявления: нативная карточка, баннер или HTML (HTML — только администратор). Справа — как выглядит на сайте. */
class AdCreativeResource extends Resource
{
    public function key(): string
    {
        return 'ad-creatives';
    }

    public function label(): string
    {
        return 'Объявления';
    }

    public function singular(): string
    {
        return 'объявление';
    }

    public function model(): string
    {
        return AdCreative::class;
    }

    public function icon(): string
    {
        return 'image';
    }

    public function query(): Builder
    {
        return AdCreative::query()->with('campaign');
    }

    public function search(): array
    {
        return ['name', 'title', 'body', 'sponsor', 'url', 'campaign.name'];
    }

    public function filters(): array
    {
        return [
            'campaign_id' => ['label' => 'Кампания', 'options' => fn () => AdCampaign::query()->orderBy('name')->pluck('name', 'id')->all(), 'apply' => fn ($q, $v) => $q->where('campaign_id', $v)],
            'type' => ['label' => 'Формат', 'options' => AdCreative::TYPES, 'apply' => fn ($q, $v) => $q->where('type', $v)],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Объявление', 'sort' => 'name', 'render' => fn (AdCreative $c) => self::html('<b>'.e($c->name).'</b><span class="block faint text-xs">'.e($c->campaign?->name).'</span>')],
            ['key' => 'type', 'label' => 'Формат', 'sort' => 'type', 'render' => fn (AdCreative $c) => AdCreative::TYPES[$c->type] ?? $c->type],
            ['key' => 'impressions', 'label' => 'Показы', 'sort' => 'impressions', 'class' => 'tabular'],
            ['key' => 'clicks', 'label' => 'Клики', 'sort' => 'clicks', 'class' => 'tabular'],
            ['key' => 'ctr', 'label' => 'CTR', 'class' => 'tabular', 'render' => fn (AdCreative $c) => $c->ctr() !== null ? number_format($c->ctr(), 2, ',', '').'%' : '—'],
            ['key' => 'is_active', 'label' => 'Включено', 'sort' => 'is_active', 'render' => fn (AdCreative $c) => self::yesNo($c->is_active)],
        ];
    }

    public function fields(?Model $model = null): array
    {
        $admin = request()->user()?->role === 'admin';
        $types = $admin ? AdCreative::TYPES : array_diff_key(AdCreative::TYPES, ['html' => 1]);

        return array_values(array_filter([
            ['name' => 'campaign_id', 'label' => 'Кампания', 'type' => 'select', 'options' => fn () => AdCampaign::query()->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['required', 'integer']],
            ['name' => 'type', 'label' => 'Формат', 'type' => 'select', 'options' => $types, 'rules' => ['required'], 'default' => 'native'],
            ['name' => 'name', 'label' => 'Внутреннее название', 'rules' => ['required', 'string', 'max:160'], 'help' => 'видно только в админке'],
            ['name' => 'url', 'label' => 'Ссылка', 'type' => 'url', 'rules' => ['nullable', 'url:http,https', 'max:1000'], 'help' => 'куда ведёт клик; UTM-метки — прямо в ссылке'],
            ['name' => 'sponsor', 'label' => 'Рекламодатель на метке', 'rules' => ['nullable', 'string', 'max:120'], 'help' => '«Реклама · Halyk Bank»', 'group' => 'Маркировка'],
            ['name' => 'disclosure', 'label' => 'Данные маркировки', 'rules' => ['nullable', 'string', 'max:255'], 'help' => 'по закону страны: erid, БИН/ИНН рекламодателя — видно по наведению на метку', 'group' => 'Маркировка'],
            ['name' => 'title', 'label' => 'Заголовок', 'rules' => ['nullable', 'string', 'max:160'], 'group' => 'Нативная карточка'],
            ['name' => 'cta', 'label' => 'Кнопка', 'rules' => ['nullable', 'string', 'max:40'], 'placeholder' => 'Подробнее', 'group' => 'Нативная карточка'],
            ['name' => 'body', 'label' => 'Текст', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:300'], 'group' => 'Нативная карточка'],
            ['name' => 'image', 'label' => 'Картинка', 'type' => 'image', 'dir' => 'ads', 'group' => 'Картинки', 'help' => 'баннер — в пропорциях места; карточка — 16:10'],
            ['name' => 'image_mobile', 'label' => 'Картинка для телефона', 'type' => 'image', 'dir' => 'ads', 'group' => 'Картинки', 'help' => 'необязательно: баннер в телефонных пропорциях места'],
            ['name' => 'alt', 'label' => 'Описание картинки', 'rules' => ['nullable', 'string', 'max:160'], 'group' => 'Картинки'],
            $admin ? ['name' => 'html', 'label' => 'HTML-объявление', 'type' => 'textarea', 'class' => 'font-mono text-xs', 'rules' => ['nullable', 'string', 'max:20000'], 'group' => 'HTML',
                'help' => 'Показывается в изолированном окне (iframe sandbox) размером с место: не видит сайт и cookie посетителя. Клики внутри не считаются.'] : null,
            ['name' => 'weight', 'label' => 'Вес внутри кампании 1–10', 'type' => 'number', 'rules' => ['required', 'integer', 'min:1', 'max:10'], 'default' => 5, 'group' => 'Показ'],
            ['name' => 'is_active', 'label' => 'Включено', 'type' => 'bool', 'default' => true, 'group' => 'Показ'],
        ]));
    }

    public function defaults(Request $request): array
    {
        return array_filter(['campaign_id' => $request->query('campaign')]);
    }

    public function saving(Model $m, array $data, Request $request): void
    {
        if ($m->type === 'html' && $request->user()?->role !== 'admin') {
            throw ValidationException::withMessages(['type' => 'HTML-объявления добавляет только администратор.']);
        }
        $missing = match ($m->type) {
            'image' => ! $m->image ? ['image' => 'Загрузите картинку баннера.'] : (! $m->url ? ['url' => 'Укажите ссылку.'] : []),
            'native' => ! $m->title ? ['title' => 'Нужен заголовок.'] : (! $m->url ? ['url' => 'Укажите ссылку.'] : []),
            'html' => ! trim((string) $m->html) ? ['html' => 'Вставьте HTML.'] : [],
            default => [],
        };
        if ($missing) {
            throw ValidationException::withMessages($missing);
        }
    }

    public function editAside(Model $m): ?string
    {
        return view('admin.ads._creative-aside', ['creative' => $m])->render();
    }

    public function title(Model $m): string
    {
        return $m->name;
    }
}
