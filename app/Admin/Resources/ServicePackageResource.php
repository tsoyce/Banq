<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\Bank;
use App\Models\Card;
use App\Models\Country;
use App\Models\ServicePackage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** A32 · Пакеты услуг банков: стоимость, условие бесплатности, привилегии, карты в пакете. */
class ServicePackageResource extends Resource
{
    public function key(): string
    {
        return 'packages';
    }

    public function label(): string
    {
        return 'Пакеты услуг';
    }

    public function singular(): string
    {
        return 'пакет услуг';
    }

    public function model(): string
    {
        return ServicePackage::class;
    }

    public function icon(): string
    {
        return 'gift';
    }

    public function exchangeEntity(): ?string
    {
        return 'service_packages';
    }

    public function query(): Builder
    {
        return ServicePackage::query()->with('bank.country')->withCount('cards');
    }

    public function search(): array
    {
        return ['name', 'slug', 'bank.name', 'bank.short_name', 'bank.code'];
    }

    public function defaultSort(): array
    {
        return ['updated_at', 'desc'];
    }

    public function filters(): array
    {
        return [
            'country' => ['label' => 'Страна', 'options' => fn () => Country::query()->ordered()->pluck('name', 'id')->all(), 'apply' => fn ($q, $v) => $q->whereHas('bank', fn ($b) => $b->where('country_id', $v))],
            'bank' => ['label' => 'Банк', 'options' => fn () => Bank::query()->inCountry(request('country') ?: null)->orderBy('name')->get()->mapWithKeys(fn ($b) => [$b->id => $b->displayName()])->all(), 'apply' => fn ($q, $v) => $q->where('bank_id', $v)],
            'segment' => ['label' => 'Сегмент', 'options' => ServicePackage::SEGMENTS, 'apply' => fn ($q, $v) => $q->where('segment', $v)],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Пакет', 'sort' => 'name', 'render' => fn (ServicePackage $p) => self::html('<b>'.e($p->name).'</b><span class="faint text-xs block">'.e(($p->bank?->country?->flag ?? '').' '.$p->bank?->displayName()).'</span>')],
            ['key' => 'segment', 'label' => 'Сегмент', 'sort' => 'segment', 'render' => fn (ServicePackage $p) => $p->segmentLabel()],
            ['key' => 'monthly_fee', 'label' => 'В месяц', 'sort' => 'monthly_fee', 'render' => fn (ServicePackage $p) => self::html('<span class="tabular">'.($p->monthly_fee === null ? '<span class="faint">—</span>' : e(money_format((float) $p->monthly_fee, currency_symbol($p->bank?->country?->currency)))).'</span>')],
            ['key' => 'cards', 'label' => 'Карт', 'sort' => 'cards_count', 'render' => fn (ServicePackage $p) => self::html('<span class="tabular">'.$p->cards_count.'</span>')],
            ['key' => 'is_active', 'label' => 'На сайте', 'sort' => 'is_active', 'render' => fn (ServicePackage $p) => self::yesNo($p->is_active)],
        ];
    }

    public function fields(?Model $model = null): array
    {
        return [
            ['name' => 'bank_id', 'label' => 'Банк', 'type' => 'select', 'span' => 2, 'rules' => ['required', 'integer', 'exists:banks,id'],
                'options' => fn () => Bank::query()->with('country')->orderBy('country_id')->orderBy('name')->get()->mapWithKeys(fn ($b) => [$b->id => trim(($b->country?->flag ?? '').' '.$b->displayName().' · '.$b->code)])->all()],
            ['name' => 'name', 'label' => 'Название', 'rules' => ['required', 'string', 'max:160']],
            ['name' => 'segment', 'label' => 'Сегмент', 'type' => 'select', 'rules' => ['required'], 'options' => ServicePackage::SEGMENTS, 'default' => 'mass'],
            ['name' => 'slug', 'label' => 'Адрес на сайте', 'help' => 'пусто — из банка и названия', 'rules' => ['nullable', 'alpha_dash', 'max:160', Rule::unique('service_packages')->ignore($model)]],
            ['name' => 'is_active', 'label' => 'Показывать на сайте', 'type' => 'bool', 'default' => true],
            ['name' => 'monthly_fee', 'label' => 'Стоимость в месяц', 'type' => 'decimal', 'group' => 'Стоимость', 'rules' => ['nullable', 'numeric', 'min:0']],
            ['name' => 'annual_fee', 'label' => 'Стоимость в год', 'type' => 'decimal', 'group' => 'Стоимость', 'rules' => ['nullable', 'numeric', 'min:0']],
            ['name' => 'fee_note', 'label' => 'Когда бесплатно', 'group' => 'Стоимость', 'span' => 2, 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'perks', 'label' => 'Что входит', 'type' => 'list', 'group' => 'Состав', 'span' => 2, 'help' => 'по одной привилегии в строке'],
            ['name' => 'cards', 'label' => 'Карты в пакете', 'type' => 'checks', 'virtual' => true, 'group' => 'Состав',
                'options' => fn () => Card::query()->with('bank')->when($model?->bank_id, fn ($q, $b) => $q->where('bank_id', $b))->orderBy('bank_id')->orderBy('name')->get()
                    ->mapWithKeys(fn ($c) => [$c->id => $c->bank->displayName().'|'.$c->name])->all(),
                'value' => fn (ServicePackage $p) => $p->cards()->pluck('cards.id')->all(), 'help' => 'карты банка пакета; у нового пакета — сохраните, затем отметьте'],
            ['name' => 'color', 'label' => 'Цвет', 'type' => 'color', 'group' => 'Прочее', 'rules' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']],
            ['name' => 'sort', 'label' => 'Порядок', 'type' => 'number', 'group' => 'Прочее', 'rules' => ['nullable', 'integer', 'min:0'], 'default' => 500],
            ['name' => 'source_url', 'label' => 'Страница на сайте банка', 'type' => 'url', 'group' => 'Прочее', 'span' => 2, 'rules' => ['nullable', 'url', 'max:255']],
            ['name' => 'description', 'label' => 'Описание', 'type' => 'textarea', 'group' => 'Прочее', 'rules' => ['nullable', 'string', 'max:10000']],
            ['name' => 'conditions', 'label' => 'Прочие условия (JSON)', 'type' => 'json', 'group' => 'Прочее'],
        ];
    }

    public function defaults(Request $request): array
    {
        return ['bank_id' => $request->query('bank')];
    }

    public function saving(Model $m, array $data, Request $request): void
    {
        $m->sort ??= 500;
        if (! $m->slug) {
            $base = Str::slug(Bank::find($m->bank_id)?->slug.'-paket-'.$m->name);
            for ($i = 1, $s = $base; ServicePackage::query()->where('slug', $s)->whereKeyNot($m->id)->exists(); $s = $base.'-'.++$i);
            $m->slug = $s;
        }
    }

    public function saved(Model $m, array $data, Request $request): void
    {
        $ids = Card::query()->where('bank_id', $m->bank_id)->whereIn('id', (array) $request->input('cards', []))->pluck('id');
        $m->cards()->sync($ids);
    }

    public function bulkActions(): array
    {
        return [
            'activate' => ['label' => 'Показать на сайте', 'run' => fn (Collection $m) => 'Показано: '.$m->each->update(['is_active' => true])->count()],
            'deactivate' => ['label' => 'Скрыть', 'run' => fn (Collection $m) => 'Скрыто: '.$m->each->update(['is_active' => false])->count()],
            ...parent::bulkActions(),
        ];
    }

    public function publicUrl(Model $m): ?string
    {
        return $m->is_active ? route('packages.show', $m) : null;
    }

    public function summary(): array
    {
        return ['Пакетов' => ServicePackage::count(), 'Премиальных' => ServicePackage::whereIn('segment', ['premium', 'private'])->count()];
    }
}
