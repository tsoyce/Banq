<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\Bank;
use App\Models\Country;
use App\Support\AdminScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/** A24 · Банки: справочник с поиском по алиасам, фильтры по стране/статусу, массовая смена статуса. */
class BankResource extends Resource
{
    public const STATUSES = ['active' => 'Работает', 'revoked' => 'Лицензия отозвана', 'merged' => 'Присоединён'];

    public function key(): string
    {
        return 'banks';
    }

    public function label(): string
    {
        return 'Банки';
    }

    public function singular(): string
    {
        return 'банк';
    }

    public function model(): string
    {
        return Bank::class;
    }

    public function icon(): string
    {
        return 'bank';
    }

    public function exchangeEntity(): ?string
    {
        return 'banks';
    }

    /** A46 · Где у записи страна — для фильтра в плашке админки и стран сотрудника. */
    public function countryScope(): ?string
    {
        return 'country_id';
    }

    public function query(): Builder
    {
        return Bank::query()->with('country')->withCount(['depositProducts', 'depositProducts as live_products_count' => fn ($q) => $q->active()->whereHas('currentRates')]);
    }

    public function search(): \Closure
    {
        return fn (Builder $q, string $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('short_name', 'like', "%{$term}%")
            ->orWhere('code', 'like', "%{$term}%")->orWhere('aliases', 'like', '%'.trim(json_encode($term, JSON_UNESCAPED_UNICODE), '"').'%')->orWhere('bin', $term));
    }

    public function defaultSort(): array
    {
        return ['sort', 'asc'];
    }

    public function filters(): array
    {
        return [
            'country' => ['label' => 'Страна', 'options' => fn () => app(AdminScope::class)->countryQuery()->pluck('name', 'id')->all(), 'apply' => fn ($q, $v) => $q->where('country_id', $v)],
            'status' => ['label' => 'Статус', 'options' => self::STATUSES, 'apply' => fn ($q, $v) => $q->where('status', $v)],
            'insured' => ['label' => 'Гарантия вкладов', 'options' => ['1' => 'Участник', '0' => 'Не участник'], 'apply' => fn ($q, $v) => $q->where('deposit_insured', $v === '1')],
            'data' => ['label' => 'Данные', 'options' => ['empty' => 'Без вкладов', 'norates' => 'Без действующих ставок', 'nofin' => 'Без финансов'], 'apply' => fn ($q, $v) => match ($v) {
                'empty' => $q->doesntHave('depositProducts'),
                'norates' => $q->whereDoesntHave('depositProducts', fn ($p) => $p->whereHas('currentRates')),
                'nofin' => $q->whereNull('assets_bn'),
            }],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Банк', 'sort' => 'name', 'render' => fn (Bank $b) => self::html(
                '<div class="flex items-center gap-3"><span class="bank-mark" style="--c: '.e($b->brand_color ?? 'var(--ink)').'">'.e($b->mark()).'</span><div><p class="font-semibold">'.e($b->displayName()).'</p><p class="faint text-xs">'.e($b->name).'</p></div></div>')],
            ['key' => 'country', 'label' => 'Страна', 'sort' => 'country_id', 'render' => fn (Bank $b) => self::html(trim(($b->country?->flagImg() ?? '').' '.e($b->country?->code ?? '—')))], // A46
            ['key' => 'code', 'label' => 'Код', 'sort' => 'code', 'class' => 'font-mono text-sm'],
            ['key' => 'products', 'label' => 'Вкладов', 'sort' => 'deposit_products_count', 'render' => fn (Bank $b) => self::html('<span class="tabular">'.$b->live_products_count.'</span><span class="faint"> / '.$b->deposit_products_count.'</span>')],
            ['key' => 'deposit_insured', 'label' => 'Гарантия', 'sort' => 'deposit_insured', 'render' => fn (Bank $b) => self::yesNo($b->deposit_insured)],
            ['key' => 'status', 'label' => 'Статус', 'sort' => 'status', 'render' => fn (Bank $b) => self::html('<span class="chip'.($b->status === 'active' ? '' : ' !text-[var(--bad)]').'">'.e(mb_strtolower(self::STATUSES[$b->status] ?? $b->status)).'</span>')],
            ['key' => 'updated_at', 'label' => 'Изменён', 'sort' => 'updated_at', 'render' => fn (Bank $b) => self::html('<span class="faint text-sm">'.$b->updated_at?->format('d.m.Y').'</span>')],
        ];
    }

    public function fields(?Model $model = null): array
    {
        $countryId = request('country_id', $model?->country_id);

        return [
            ['name' => 'country_id', 'label' => 'Страна', 'type' => 'select', 'options' => fn () => app(AdminScope::class)->countryQuery()->get()->mapWithKeys(fn ($c) => [$c->id => trim($c->flag.' '.$c->name)])->all(), 'rules' => ['required', 'integer', 'exists:countries,id']],
            ['name' => 'code', 'label' => 'Код', 'help' => 'ключ для импорта: BCC, FFIN…', 'class' => 'font-mono uppercase', 'rules' => ['required', 'string', 'max:32', Rule::unique('banks')->where('country_id', $countryId)->ignore($model)]],
            ['name' => 'name', 'label' => 'Полное название', 'span' => 2, 'rules' => ['required', 'string', 'max:160']],
            ['name' => 'short_name', 'label' => 'Короткое имя', 'rules' => ['nullable', 'string', 'max:80']],
            ['name' => 'slug', 'label' => 'Адрес на сайте', 'help' => 'пусто — из имени', 'rules' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('banks')->ignore($model)]],
            ['name' => 'name_kz', 'label' => 'Название на языке страны', 'span' => 2, 'rules' => ['nullable', 'string', 'max:160']],
            ['name' => 'aliases', 'label' => 'Алиасы', 'type' => 'list', 'span' => 2, 'help' => 'как ещё пишут банк в таблицах и источниках — по одному в строке; по ним импорт находит банк'],
            ['name' => 'status', 'label' => 'Статус', 'type' => 'select', 'options' => self::STATUSES, 'rules' => ['required'], 'default' => 'active'],
            ['name' => 'sort', 'label' => 'Порядок', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0'], 'default' => 500],
            ['name' => 'description', 'label' => 'Описание', 'type' => 'textarea', 'span' => 2, 'rules' => ['nullable', 'string', 'max:5000']],

            ['name' => 'brand_color', 'label' => 'Фирменный цвет', 'type' => 'color', 'group' => 'Бренд и контакты', 'rules' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']],
            ['name' => 'logo', 'label' => 'Логотип (путь или URL)', 'group' => 'Бренд и контакты', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'website', 'label' => 'Сайт', 'type' => 'url', 'group' => 'Бренд и контакты', 'rules' => ['nullable', 'url', 'max:255']],
            ['name' => 'phone', 'label' => 'Телефон', 'group' => 'Бренд и контакты', 'rules' => ['nullable', 'string', 'max:32']],

            ['name' => 'legal_name', 'label' => 'Юр. название', 'group' => 'Реквизиты и надёжность', 'span' => 2, 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'bin', 'label' => 'Рег. номер (БИН)', 'group' => 'Реквизиты и надёжность', 'rules' => ['nullable', 'string', 'max:12']],
            ['name' => 'swift', 'label' => 'SWIFT', 'group' => 'Реквизиты и надёжность', 'class' => 'font-mono uppercase', 'rules' => ['nullable', 'string', 'max:11']],
            ['name' => 'license', 'label' => 'Лицензия', 'group' => 'Реквизиты и надёжность', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'founded_year', 'label' => 'Год основания', 'type' => 'number', 'group' => 'Реквизиты и надёжность', 'rules' => ['nullable', 'integer', 'min:1700', 'max:2100']],
            ['name' => 'deposit_insured', 'label' => 'Участник системы гарантирования вкладов', 'type' => 'bool', 'group' => 'Реквизиты и надёжность', 'default' => true],
            ['name' => 'assets_bn', 'label' => 'Активы, млрд', 'type' => 'decimal', 'group' => 'Реквизиты и надёжность', 'rules' => ['nullable', 'numeric', 'min:0']],
            ['name' => 'capital_bn', 'label' => 'Капитал, млрд', 'type' => 'decimal', 'group' => 'Реквизиты и надёжность', 'rules' => ['nullable', 'numeric', 'min:0']],
            ['name' => 'financials_at', 'label' => 'Финансы на дату', 'type' => 'date', 'group' => 'Реквизиты и надёжность', 'rules' => ['nullable', 'date']],
        ];
    }

    public function defaults(Request $request): array
    {
        return ['country_id' => country()?->id];
    }

    public function saving(Model $m, array $data, Request $request): void
    {
        if (! $m->slug) {
            $m->slug = null; // соберётся из имени в Bank::booted
        }
    }

    public function bulkActions(): array
    {
        $status = fn (string $s) => fn (Collection $m) => 'Статус «'.mb_strtolower(self::STATUSES[$s]).'»: '.$m->each->update(['status' => $s])->count();

        return [
            'active' => ['label' => 'Статус: работает', 'run' => $status('active')],
            'revoked' => ['label' => 'Статус: лицензия отозвана', 'run' => $status('revoked')],
            'insured' => ['label' => 'Отметить участниками гарантии', 'run' => fn (Collection $m) => 'Отмечено: '.$m->each->update(['deposit_insured' => true])->count()],
            ...parent::bulkActions(),
        ];
    }

    public function title(Model $m): string
    {
        return $m->displayName();
    }

    public function publicUrl(Model $m): ?string
    {
        return $m->status === 'active' ? route('banks.show', $m) : null;
    }

    public function editAside(Model $m): ?string
    {
        return view('admin.resource.aside-bank', ['bank' => $m])->render();
    }

    public function summary(): array
    {
        return ['Всего' => Bank::count(), 'Работают' => Bank::active()->count(), 'Без ставок' => Bank::whereDoesntHave('depositProducts', fn ($p) => $p->whereHas('currentRates'))->count()];
    }
}
