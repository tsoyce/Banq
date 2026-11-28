<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Enums\DepositType;
use App\Models\Bank;
use App\Models\Country;
use App\Models\DepositProduct;
use App\Models\DepositRate;
use App\Support\AdminScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** A24 · Вклады: CRUD продукта + на странице правки — сетка действующих ставок, история и добавление новой ставки. */
class DepositProductResource extends Resource
{
    public function key(): string
    {
        return 'deposits';
    }

    public function label(): string
    {
        return 'Вклады';
    }

    public function singular(): string
    {
        return 'вклад';
    }

    public function model(): string
    {
        return DepositProduct::class;
    }

    public function icon(): string
    {
        return 'piggy';
    }

    public function exchangeEntity(): ?string
    {
        return 'deposit_products';
    }

    /** A46 · Где у записи страна — для фильтра в плашке админки и стран сотрудника. */
    public function countryScope(): ?string
    {
        return 'bank.country_id';
    }

    public function query(): Builder
    {
        return DepositProduct::query()->with('bank.country')->withCount(['currentRates', 'rates'])->withMax('currentRates as best_rate', 'rate')->withMax('rates as last_rate_at', 'valid_from');
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
            'country' => ['label' => 'Страна', 'options' => fn () => app(AdminScope::class)->countryQuery()->pluck('name', 'id')->all(), 'apply' => fn ($q, $v) => $q->whereHas('bank', fn ($b) => $b->where('country_id', $v))],
            'bank' => ['label' => 'Банк', 'options' => fn () => Bank::query()->inCountry(request('country') ?: null)->orderBy('name')->get()->mapWithKeys(fn ($b) => [$b->id => $b->displayName()])->all(), 'apply' => fn ($q, $v) => $q->where('bank_id', $v)],
            'type' => ['label' => 'Тип', 'options' => collect(DepositType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all(), 'apply' => fn ($q, $v) => $q->where('type', $v)],
            'active' => ['label' => 'На сайте', 'options' => ['1' => 'Активные', '0' => 'Скрытые'], 'apply' => fn ($q, $v) => $q->where('is_active', $v === '1')],
            'rates' => ['label' => 'Ставки', 'options' => ['none' => 'Без действующих', 'stale' => 'Не обновлялись 60+ дней'], 'apply' => fn ($q, $v) => $v === 'none'
                ? $q->doesntHave('currentRates')
                : $q->whereHas('currentRates')->whereDoesntHave('currentRates', fn ($r) => $r->where('valid_from', '>=', today()->subDays(60)))],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Вклад', 'sort' => 'name', 'render' => fn (DepositProduct $p) => self::html(
                '<p class="font-semibold">'.e($p->name).'</p><p class="faint text-xs">'.($p->bank?->country?->flagImg() ?? '').' '.e($p->bank?->displayName()).' · <span class="font-mono">'.e($p->slug).'</span></p>')],
            ['key' => 'type', 'label' => 'Тип', 'sort' => 'type', 'render' => fn (DepositProduct $p) => $p->type?->label()],
            ['key' => 'best_rate', 'label' => 'Лучшая ставка', 'sort' => 'best_rate', 'render' => fn (DepositProduct $p) => self::html('<b class="tabular">'.rate_format($p->best_rate).'</b>')],
            ['key' => 'rates', 'label' => 'Ставок', 'sort' => 'current_rates_count', 'render' => fn (DepositProduct $p) => self::html('<span class="tabular">'.$p->current_rates_count.'</span><span class="faint"> / '.$p->rates_count.'</span>')],
            ['key' => 'last_rate_at', 'label' => 'Ставки от', 'sort' => 'last_rate_at', 'render' => fn (DepositProduct $p) => self::html($p->last_rate_at
                ? '<span class="text-sm '.(now()->diffInDays($p->last_rate_at, true) > 60 ? 'text-[var(--warn)]' : 'faint').'">'.Carbon::parse($p->last_rate_at)->format('d.m.Y').'</span>' : '<span class="faint">—</span>')],
            ['key' => 'is_active', 'label' => 'На сайте', 'sort' => 'is_active', 'render' => fn (DepositProduct $p) => self::yesNo($p->is_active)],
        ];
    }

    public function fields(?Model $model = null): array
    {
        return [
            ['name' => 'bank_id', 'label' => 'Банк', 'type' => 'select', 'span' => 2, 'rules' => ['required', 'integer', 'exists:banks,id'],
                'options' => fn () => Bank::query()->with('country')->tap(fn ($q) => app(AdminScope::class)->apply($q, 'country_id', selection: false))->orderBy('country_id')->orderBy('name')->get()->mapWithKeys(fn ($b) => [$b->id => trim(($b->country?->flag ?? '').' '.$b->displayName().' · '.$b->code)])->all()],
            ['name' => 'name', 'label' => 'Название', 'rules' => ['required', 'string', 'max:160']],
            ['name' => 'type', 'label' => 'Тип', 'type' => 'select', 'rules' => ['required'], 'options' => collect(DepositType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label().' — '.mb_strtolower($t->hint())])->all()],
            ['name' => 'slug', 'label' => 'Адрес на сайте', 'help' => 'пусто — из банка и названия', 'rules' => ['nullable', 'alpha_dash', 'max:160', Rule::unique('deposit_products')->ignore($model)]],
            ['name' => 'currencies', 'label' => 'Валюты', 'type' => 'list', 'help' => 'по одной в строке', 'placeholder' => "KZT\nUSD"],
            ['name' => 'is_active', 'label' => 'Показывать на сайте', 'type' => 'bool', 'default' => true],
            ['name' => 'online_opening', 'label' => 'Открытие онлайн', 'type' => 'bool', 'default' => true],

            ['name' => 'min_amount', 'label' => 'Мин. сумма', 'type' => 'decimal', 'group' => 'Условия', 'rules' => ['nullable', 'numeric', 'min:0']],
            ['name' => 'max_amount', 'label' => 'Макс. сумма', 'type' => 'decimal', 'group' => 'Условия', 'rules' => ['nullable', 'numeric', 'min:0']],
            ['name' => 'term_min', 'label' => 'Срок от, мес.', 'type' => 'number', 'group' => 'Условия', 'rules' => ['nullable', 'integer', 'min:0', 'max:600']],
            ['name' => 'term_max', 'label' => 'Срок до, мес.', 'type' => 'number', 'group' => 'Условия', 'rules' => ['nullable', 'integer', 'min:0', 'max:600']],
            ['name' => 'capitalization', 'label' => 'Капитализация', 'type' => 'select', 'group' => 'Условия', 'options' => ['' => '—', 'monthly' => 'Ежемесячно', 'end' => 'В конце срока', 'none' => 'Без капитализации (выплата)']],
            ['name' => 'can_replenish', 'label' => 'Можно пополнять', 'type' => 'bool', 'group' => 'Условия', 'default' => true],
            ['name' => 'can_withdraw', 'label' => 'Можно частично снимать', 'type' => 'bool', 'group' => 'Условия', 'help' => 'несрочный — да; срочный — только пополнение; сберегательный — ни того, ни другого'],
            ['name' => 'source_url', 'label' => 'Страница на сайте банка', 'type' => 'url', 'group' => 'Условия', 'span' => 2, 'rules' => ['nullable', 'url', 'max:255']],
            ['name' => 'description', 'label' => 'Описание', 'type' => 'textarea', 'group' => 'Условия', 'span' => 2, 'rules' => ['nullable', 'string', 'max:10000']],
            ['name' => 'conditions', 'label' => 'Прочие условия (JSON)', 'type' => 'json', 'group' => 'Условия', 'span' => 2, 'help' => '{"досрочное расторжение": "по ставке до востребования"}'],
        ];
    }

    public function defaults(Request $request): array
    {
        $bank = Bank::find($request->query('bank'));

        return ['bank_id' => $bank?->id, 'currencies' => [$bank?->country?->currency ?? country()?->currency ?? 'KZT']];
    }

    public function saving(Model $m, array $data, Request $request): void
    {
        $type = $m->type instanceof DepositType ? $m->type : DepositType::from($m->type);
        if (! $m->slug) {
            $base = Str::slug(Bank::find($m->bank_id)?->slug.'-'.$m->name.'-'.$type->value);
            for ($i = 1, $s = $base; DepositProduct::query()->where('slug', $s)->whereKeyNot($m->id)->exists(); $s = $base.'-'.++$i);
            $m->slug = $s;
        }
    }

    public function bulkActions(): array
    {
        return [
            'activate' => ['label' => 'Показать на сайте', 'run' => fn (Collection $m) => 'Показано: '.$m->each->update(['is_active' => true])->count()],
            'deactivate' => ['label' => 'Скрыть', 'run' => fn (Collection $m) => 'Скрыто: '.$m->each->update(['is_active' => false])->count()],
            'close' => ['label' => 'Закрыть действующие ставки', 'confirm' => 'Закрыть все действующие ставки выбранных вкладов сегодняшним днём? История останется.',
                'run' => fn (Collection $m) => 'Закрыто ставок: '.DepositRate::query()->whereIn('deposit_product_id', $m->modelKeys())->whereNull('valid_to')->update(['valid_to' => today()])],
            ...parent::bulkActions(),
        ];
    }

    public function title(Model $m): string
    {
        return $m->name;
    }

    public function publicUrl(Model $m): ?string
    {
        return $m->is_active ? route('deposits.show', $m) : null;
    }

    public function editAside(Model $m): ?string
    {
        $m->load(['bank.country', 'rates' => fn ($q) => $q->orderByDesc('valid_from')->orderByDesc('id')]);

        return view('admin.resource.aside-deposit', ['product' => $m])->render();
    }

    public function summary(): array
    {
        return ['Всего' => DepositProduct::count(), 'С действующими ставками' => DepositProduct::whereHas('currentRates')->count(), 'Скрыто' => DepositProduct::where('is_active', false)->count()];
    }
}
