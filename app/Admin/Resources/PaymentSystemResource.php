<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\PaymentSystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/** A32 · Платёжные системы: справочник + на странице правки — уровни карт системы. */
class PaymentSystemResource extends Resource
{
    public function key(): string
    {
        return 'payment-systems';
    }

    public function label(): string
    {
        return 'Платёжные системы';
    }

    public function singular(): string
    {
        return 'платёжная система';
    }

    public function model(): string
    {
        return PaymentSystem::class;
    }

    public function icon(): string
    {
        return 'globe';
    }

    public function exchangeEntity(): ?string
    {
        return 'payment_systems';
    }

    public function query(): Builder
    {
        return PaymentSystem::query()->withCount(['tiers', 'cards']);
    }

    public function search(): array
    {
        return ['name', 'short_name', 'code', 'slug'];
    }

    public function defaultSort(): array
    {
        return ['sort', 'asc'];
    }

    public function filters(): array
    {
        return [
            'scope' => ['label' => 'Охват', 'options' => PaymentSystem::SCOPES, 'apply' => fn ($q, $v) => $q->where('scope', $v)],
            'active' => ['label' => 'На сайте', 'options' => ['1' => 'Активные', '0' => 'Скрытые'], 'apply' => fn ($q, $v) => $q->where('is_active', $v === '1')],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Система', 'sort' => 'name', 'render' => fn (PaymentSystem $p) => self::html(
                '<span class="flex items-center gap-3"><span class="bank-mark !w-9 !h-9 !text-[0.55rem]" style="--c: '.e($p->color ?? '#6d28d9').'">'.e($p->mark()).'</span><span><b>'.e($p->name).'</b><span class="faint text-xs block font-mono">'.e($p->code).'</span></span></span>')],
            ['key' => 'scope', 'label' => 'Охват', 'sort' => 'scope', 'render' => fn (PaymentSystem $p) => $p->scopeLabel().($p->home_country ? ' · '.$p->home_country : '')],
            ['key' => 'tiers', 'label' => 'Уровней', 'sort' => 'tiers_count', 'render' => fn (PaymentSystem $p) => self::html('<span class="tabular">'.$p->tiers_count.'</span>')],
            ['key' => 'cards', 'label' => 'Карт', 'sort' => 'cards_count', 'render' => fn (PaymentSystem $p) => self::html('<span class="tabular">'.$p->cards_count.'</span>')],
            ['key' => 'sort', 'label' => 'Порядок', 'sort' => 'sort', 'render' => fn (PaymentSystem $p) => self::html('<span class="faint tabular">'.$p->sort.'</span>')],
            ['key' => 'is_active', 'label' => 'На сайте', 'sort' => 'is_active', 'render' => fn (PaymentSystem $p) => self::yesNo($p->is_active)],
        ];
    }

    public function fields(?Model $model = null): array
    {
        return [
            ['name' => 'name', 'label' => 'Название', 'rules' => ['required', 'string', 'max:160']],
            ['name' => 'code', 'label' => 'Код', 'help' => 'латиницей: VISA, MASTERCARD', 'rules' => ['required', 'string', 'max:32', Rule::unique('payment_systems')->ignore($model)], 'class' => 'font-mono uppercase'],
            ['name' => 'short_name', 'label' => 'Короткое имя', 'rules' => ['nullable', 'string', 'max:80']],
            ['name' => 'slug', 'label' => 'Адрес на сайте', 'help' => 'пусто — из имени', 'rules' => ['nullable', 'alpha_dash', 'max:64', Rule::unique('payment_systems')->ignore($model)]],
            ['name' => 'scope', 'label' => 'Охват', 'type' => 'select', 'rules' => ['required'], 'options' => PaymentSystem::SCOPES, 'default' => 'international'],
            ['name' => 'home_country', 'label' => 'Страна происхождения (ISO-2)', 'rules' => ['nullable', 'alpha', 'size:2'], 'placeholder' => 'US', 'class' => 'uppercase'],
            ['name' => 'aliases', 'label' => 'Как ещё пишут', 'type' => 'list', 'help' => 'для импорта: China UnionPay, Юнионпэй'],
            ['name' => 'countries', 'label' => 'Где национальная (ISO-2)', 'type' => 'list', 'help' => 'по одной в строке'],
            ['name' => 'is_active', 'label' => 'Показывать на сайте', 'type' => 'bool', 'default' => true],

            ['name' => 'color', 'label' => 'Цвет', 'type' => 'color', 'group' => 'Оформление', 'rules' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']],
            ['name' => 'color_2', 'label' => 'Второй цвет градиента', 'type' => 'color', 'group' => 'Оформление', 'rules' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']],
            // A54 · знак системы: загрузка файла; второй вариант — латиница/кириллица (MIR / МИР) или монохром
            ['name' => 'logo', 'label' => 'Знак системы', 'type' => 'image', 'group' => 'Оформление', 'help' => 'пусто — буквы марки; загружайте только знак, который вправе использовать'],
            ['name' => 'logo_alt', 'label' => 'Второй вариант знака', 'type' => 'image', 'group' => 'Оформление', 'help' => 'например, латиницей вместо кириллицы или монохромный'],
            ['name' => 'meta.mark', 'label' => 'Буквы марки', 'group' => 'Оформление', 'help' => 'до 4 символов: MC, UP', 'rules' => ['nullable', 'string', 'max:4']],
            ['name' => 'sort', 'label' => 'Порядок', 'type' => 'number', 'group' => 'Оформление', 'rules' => ['nullable', 'integer', 'min:0'], 'default' => 500],

            ['name' => 'website', 'label' => 'Сайт', 'type' => 'url', 'group' => 'О системе', 'rules' => ['nullable', 'url', 'max:255']],
            ['name' => 'founded_year', 'label' => 'Год основания', 'type' => 'number', 'group' => 'О системе', 'rules' => ['nullable', 'integer', 'min:1800', 'max:2100']],
            ['name' => 'headquarters', 'label' => 'Штаб-квартира', 'group' => 'О системе', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'wallets', 'label' => 'Мобильные кошельки', 'type' => 'checks', 'group' => 'О системе', 'options' => config('banq.cards.wallets')],
            ['name' => 'description', 'label' => 'Описание', 'type' => 'textarea', 'group' => 'О системе', 'rules' => ['nullable', 'string', 'max:10000']],
        ];
    }

    public function fill(Model $m, array $data): void
    {
        parent::fill($m, $data);
        $m->sort ??= 500;
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
        return $m->is_active ? route('payment-systems.show', $m) : null;
    }

    public function editAside(Model $m): ?string
    {
        $m->load(['tiers' => fn ($q) => $q->withCount('cards')]);

        return view('admin.resource.aside-payment-system', ['system' => $m])->render();
    }

    public function summary(): array
    {
        return ['Систем' => PaymentSystem::count(), 'Международных' => PaymentSystem::where('scope', 'international')->count(), 'Национальных' => PaymentSystem::where('scope', 'national')->count()];
    }
}
