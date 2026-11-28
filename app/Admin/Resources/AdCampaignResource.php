<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\AdCampaign;
use App\Models\AdSlot;
use App\Models\AdStat;
use App\Models\Country;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** A51 · Реклама → Кампании: период, страны, места, приоритет, частота на посетителя, бюджет; справа — объявления и статистика по дням. */
class AdCampaignResource extends Resource
{
    public function key(): string
    {
        return 'ad-campaigns';
    }

    public function label(): string
    {
        return 'Кампании';
    }

    public function singular(): string
    {
        return 'кампанию';
    }

    public function model(): string
    {
        return AdCampaign::class;
    }

    public function icon(): string
    {
        return 'spark';
    }

    public function description(): ?string
    {
        return 'Своя реклама без посредников: кто, когда, где и как часто. Объявления добавляются внутри кампании.';
    }

    public function query(): Builder
    {
        return AdCampaign::query()->withCount('creatives');
    }

    public function search(): array
    {
        return ['name', 'advertiser', 'notes'];
    }

    public function filters(): array
    {
        return ['is_active' => ['label' => 'Состояние', 'options' => ['1' => 'Включены', '0' => 'На паузе'], 'apply' => fn ($q, $v) => $q->where('is_active', (bool) $v)]];
    }

    public function bulkActions(): array
    {
        return [
            'pause' => ['label' => 'Поставить на паузу', 'run' => fn (Collection $m) => 'На паузе: '.$m->each(fn ($c) => $c->update(['is_active' => false]))->count()],
            'resume' => ['label' => 'Включить', 'run' => fn (Collection $m) => 'Включено: '.$m->each(fn ($c) => $c->update(['is_active' => true]))->count()],
        ] + parent::bulkActions();
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Кампания', 'sort' => 'name', 'render' => fn (AdCampaign $c) => self::html('<b>'.e($c->name).'</b><span class="block faint text-xs">'.e($c->advertiser ?: '—').' · '.$c->creatives_count.' '.plural($c->creatives_count, 'объявление', 'объявления', 'объявлений').'</span>')],
            ['key' => 'state', 'label' => 'Состояние', 'render' => fn (AdCampaign $c) => self::html('<span class="chip '.($c->state() === 'running' ? '!text-[var(--ok)]' : 'faint').'">'.e($c->stateLabel()).'</span>')],
            ['key' => 'period', 'label' => 'Период', 'sort' => 'starts_at', 'render' => fn (AdCampaign $c) => ($c->starts_at?->format('d.m.Y') ?? '…').' — '.($c->ends_at?->format('d.m.Y') ?? '…')],
            ['key' => 'impressions', 'label' => 'Показы', 'sort' => 'impressions', 'class' => 'tabular', 'render' => fn (AdCampaign $c) => number_format($c->impressions, 0, ',', ' ').($c->max_impressions ? ' / '.number_format($c->max_impressions, 0, ',', ' ') : '')],
            ['key' => 'clicks', 'label' => 'Клики', 'sort' => 'clicks', 'class' => 'tabular'],
            ['key' => 'ctr', 'label' => 'CTR', 'class' => 'tabular', 'render' => fn (AdCampaign $c) => $c->ctr() !== null ? number_format($c->ctr(), 2, ',', '').'%' : '—'],
        ];
    }

    public function fields(?Model $model = null): array
    {
        return [
            ['name' => 'name', 'label' => 'Название', 'rules' => ['required', 'string', 'max:160']],
            ['name' => 'advertiser', 'label' => 'Рекламодатель', 'rules' => ['nullable', 'string', 'max:160']],
            ['name' => 'is_active', 'label' => 'Включена', 'type' => 'bool', 'default' => true],
            ['name' => 'priority', 'label' => 'Приоритет 1–10', 'type' => 'number', 'rules' => ['required', 'integer', 'min:1', 'max:10'], 'default' => 5, 'help' => 'чем выше, тем чаще среди подходящих кампаний'],
            ['name' => 'starts_at', 'label' => 'Начало', 'type' => 'datetime', 'rules' => ['nullable', 'date'], 'group' => 'Когда и где'],
            ['name' => 'ends_at', 'label' => 'Окончание', 'type' => 'datetime', 'rules' => ['nullable', 'date'], 'group' => 'Когда и где'],
            ['name' => 'countries', 'label' => 'Страны', 'type' => 'checks', 'options' => fn () => Country::query()->ordered()->pluck('name', 'code')->all(), 'group' => 'Когда и где', 'help' => 'ничего не отмечено — все страны'],
            ['name' => 'slots', 'label' => 'Места', 'type' => 'checks', 'options' => fn () => AdSlot::query()->orderBy('sort')->pluck('name', 'key')->all(), 'group' => 'Когда и где', 'help' => 'ничего не отмечено — все места, где подходит формат объявления'],
            ['name' => 'freq_cap', 'label' => 'Показов одному посетителю в день', 'type' => 'number', 'rules' => ['required', 'integer', 'min:0', 'max:100'], 'default' => 3, 'group' => 'Ограничения', 'help' => '0 — без ограничения; 2–3 — не надоедает'],
            ['name' => 'max_impressions', 'label' => 'Бюджет показов', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:1'], 'group' => 'Ограничения', 'help' => 'после — кампания останавливается сама'],
            ['name' => 'max_clicks', 'label' => 'Бюджет кликов', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:1'], 'group' => 'Ограничения'],
            ['name' => 'notes', 'label' => 'Заметки (договор, контакты)', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:5000'], 'group' => 'Ограничения'],
        ];
    }

    public function saving(Model $m, array $data, Request $request): void
    {
        if ($m->starts_at && $m->ends_at && $m->ends_at->lte($m->starts_at)) {
            throw ValidationException::withMessages(['ends_at' => 'Окончание должно быть позже начала.']);
        }
    }

    public function editAside(Model $m): ?string
    {
        $days = AdStat::query()->where('campaign_id', $m->id)->where('date', '>=', today()->subDays(29))
            ->selectRaw('date, sum(impressions) as v, sum(clicks) as c')->groupBy('date')->orderBy('date')->get()
            ->keyBy(fn ($r) => \Illuminate\Support\Carbon::parse($r->date)->toDateString());

        return view('admin.ads._campaign-aside', ['campaign' => $m, 'creatives' => $m->creatives()->orderByDesc('is_active')->orderBy('id')->get(), 'days' => $days])->render();
    }

    public function title(Model $m): string
    {
        return $m->name;
    }

    public function summary(): array
    {
        return ['Идут' => AdCampaign::query()->get()->filter(fn ($c) => $c->state() === 'running')->count(), 'Показов' => (int) AdCampaign::sum('impressions'), 'Кликов' => (int) AdCampaign::sum('clicks')];
    }
}
