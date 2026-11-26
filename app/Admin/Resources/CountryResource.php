<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\Country;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/** A24 · Страны в админке: валюта, регулятор, ЦБ, гарантирование вкладов с лимитами. */
class CountryResource extends Resource
{
    public function key(): string
    {
        return 'countries';
    }

    public function label(): string
    {
        return 'Страны';
    }

    public function singular(): string
    {
        return 'страну';
    }

    public function model(): string
    {
        return Country::class;
    }

    public function icon(): string
    {
        return 'globe';
    }

    public function description(): ?string
    {
        return 'Всё национальное — здесь: валюта, регулятор, система гарантирования вкладов. Банки привязаны к стране.';
    }

    public function exchangeEntity(): ?string
    {
        return 'countries';
    }

    public function query(): Builder
    {
        return Country::query()->withCount('banks');
    }

    public function search(): array
    {
        return ['name', 'code', 'name_en', 'currency'];
    }

    public function defaultSort(): array
    {
        return ['sort', 'asc'];
    }

    public function filters(): array
    {
        return ['active' => ['label' => 'Видимость', 'options' => ['1' => 'На сайте', '0' => 'Скрыта'], 'apply' => fn ($q, $v) => $q->where('is_active', $v === '1')]];
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Страна', 'sort' => 'name', 'render' => fn (Country $c) => self::html('<span class="text-xl mr-2">'.e($c->flag).'</span><b>'.e($c->name).'</b> <span class="faint text-xs">'.e($c->name_en).'</span>')],
            ['key' => 'code', 'label' => 'Код', 'sort' => 'code', 'class' => 'font-mono'],
            ['key' => 'currency', 'label' => 'Валюта', 'sort' => 'currency', 'render' => fn (Country $c) => $c->currency.' '.$c->currency_symbol],
            ['key' => 'guarantee', 'label' => 'Гарантия вкладов', 'render' => fn (Country $c) => $c->guaranteeName() ?? '—'],
            ['key' => 'banks_count', 'label' => 'Банков', 'sort' => 'banks_count', 'class' => 'tabular'],
            ['key' => 'is_active', 'label' => 'На сайте', 'sort' => 'is_active', 'render' => fn (Country $c) => self::yesNo($c->is_active)],
            ['key' => 'sort', 'label' => 'Порядок', 'sort' => 'sort', 'class' => 'tabular faint'],
        ];
    }

    public function fields(?Model $model = null): array
    {
        $limit = fn ($k, $l) => ['name' => "deposit_guarantee.limits.{$k}", 'label' => $l, 'type' => 'number', 'group' => 'Гарантирование вкладов', 'rules' => ['nullable', 'integer', 'min:0'], 'help' => 'в нацвалюте'];

        return [
            ['name' => 'name', 'label' => 'Название', 'rules' => ['required', 'string', 'max:120'], 'placeholder' => 'Казахстан'],
            ['name' => 'code', 'label' => 'ISO-код', 'rules' => ['required', 'alpha', 'size:2', Rule::unique('countries')->ignore($model)], 'placeholder' => 'KZ', 'class' => 'font-mono uppercase'],
            ['name' => 'slug', 'label' => 'В URL (?country=)', 'rules' => ['nullable', 'alpha_dash', 'max:32', Rule::unique('countries')->ignore($model)], 'placeholder' => 'kz'],
            ['name' => 'flag', 'label' => 'Флаг (эмодзи)', 'rules' => ['nullable', 'string', 'max:16'], 'placeholder' => '🇰🇿'],
            ['name' => 'name_genitive', 'label' => 'Родительный падеж', 'help' => '«банки Казахстана»', 'rules' => ['nullable', 'string', 'max:120']],
            ['name' => 'name_locative', 'label' => 'Предложный', 'help' => '«в Казахстане»', 'rules' => ['nullable', 'string', 'max:120']],
            ['name' => 'name_en', 'label' => 'По-английски', 'rules' => ['nullable', 'string', 'max:120']],
            ['name' => 'name_native', 'label' => 'На языке страны', 'rules' => ['nullable', 'string', 'max:120']],
            ['name' => 'is_active', 'label' => 'Показывать на сайте', 'type' => 'bool', 'default' => true],
            ['name' => 'sort', 'label' => 'Порядок', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0'], 'default' => 500],

            ['name' => 'currency', 'label' => 'Основная валюта', 'group' => 'Валюта и регион', 'rules' => ['required', 'alpha', 'size:3'], 'placeholder' => 'KZT', 'class' => 'font-mono uppercase'],
            ['name' => 'currency_symbol', 'label' => 'Знак валюты', 'group' => 'Валюта и регион', 'rules' => ['nullable', 'string', 'max:8'], 'placeholder' => '₸'],
            ['name' => 'currencies', 'label' => 'Валюты вкладов', 'type' => 'list', 'group' => 'Валюта и регион', 'help' => 'по одной в строке, по порядку показа', 'placeholder' => "KZT\nUSD\nEUR"],
            ['name' => 'timezone', 'label' => 'Часовой пояс', 'group' => 'Валюта и регион', 'rules' => ['nullable', 'timezone'], 'placeholder' => 'Asia/Almaty'],
            ['name' => 'locale', 'label' => 'Язык', 'group' => 'Валюта и регион', 'rules' => ['nullable', 'string', 'max:10'], 'default' => 'ru'],
            ['name' => 'phone_code', 'label' => 'Телефонный код', 'group' => 'Валюта и регион', 'rules' => ['nullable', 'string', 'max:8'], 'placeholder' => '+7'],

            ['name' => 'regulator.short', 'label' => 'Регулятор: кратко', 'group' => 'Регулятор и центробанк', 'placeholder' => 'АРРФР', 'rules' => ['nullable', 'string', 'max:40']],
            ['name' => 'regulator.url', 'label' => 'Регулятор: сайт', 'group' => 'Регулятор и центробанк', 'rules' => ['nullable', 'url', 'max:255']],
            ['name' => 'regulator.name', 'label' => 'Регулятор: полное название', 'group' => 'Регулятор и центробанк', 'span' => 2, 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'central_bank.short', 'label' => 'Центробанк: кратко', 'group' => 'Регулятор и центробанк', 'placeholder' => 'НБРК', 'rules' => ['nullable', 'string', 'max:40']],
            ['name' => 'central_bank.url', 'label' => 'Центробанк: сайт', 'group' => 'Регулятор и центробанк', 'rules' => ['nullable', 'url', 'max:255']],
            ['name' => 'central_bank.name', 'label' => 'Центробанк: полное название', 'group' => 'Регулятор и центробанк', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'central_bank.base_rate', 'label' => 'Базовая ставка, %', 'type' => 'decimal', 'group' => 'Регулятор и центробанк', 'rules' => ['nullable', 'numeric', 'min:0', 'max:500']],

            ['name' => 'deposit_guarantee.short', 'label' => 'Система: кратко', 'group' => 'Гарантирование вкладов', 'placeholder' => 'КФГД', 'rules' => ['nullable', 'string', 'max:40']],
            ['name' => 'deposit_guarantee.url', 'label' => 'Сайт', 'group' => 'Гарантирование вкладов', 'rules' => ['nullable', 'url', 'max:255']],
            ['name' => 'deposit_guarantee.name', 'label' => 'Полное название', 'group' => 'Гарантирование вкладов', 'span' => 2, 'rules' => ['nullable', 'string', 'max:255']],
            $limit('savings', 'Лимит: сберегательные'), $limit('term', 'Лимит: срочные'), $limit('demand', 'Лимит: несрочные'), $limit('foreign', 'Лимит: в иностранной валюте'),
            ['name' => 'meta', 'label' => 'Прочее (JSON)', 'type' => 'json', 'group' => 'Дополнительно', 'span' => 2, 'help' => 'любые данные страны, ключ → значение'],
        ];
    }

    public function bulkActions(): array
    {
        return [
            'show' => ['label' => 'Показать на сайте', 'run' => fn (Collection $m) => 'Показано: '.$m->each->update(['is_active' => true])->count()],
            'hide' => ['label' => 'Скрыть', 'run' => fn (Collection $m) => 'Скрыто: '.$m->each->update(['is_active' => false])->count()],
            'delete' => ['label' => 'Удалить', 'danger' => true, 'confirm' => 'Удалить выбранные страны? Страну с банками удалить нельзя.',
                'run' => function (Collection $m) {
                    $free = $m->filter(fn (Country $c) => ! $c->banks()->exists());
                    $free->each->delete();

                    return 'Удалено: '.$free->count().($m->count() > $free->count() ? ', пропущено стран с банками: '.($m->count() - $free->count()) : '');
                }],
        ];
    }

    public function title(Model $m): string
    {
        return trim($m->flag.' '.$m->name);
    }

    public function summary(): array
    {
        return ['Стран' => Country::count(), 'На сайте' => Country::active()->count()];
    }
}
