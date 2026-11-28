<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Enums\CardKind;
use App\Models\Bank;
use App\Models\Card;
use App\Models\Country;
use App\Models\PaymentSystem;
use App\Support\AdminScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** A32 · Банковские карты: условия по группам, платёжные системы и уровни флажками (card_networks). */
class CardResource extends Resource
{
    public function key(): string
    {
        return 'cards';
    }

    public function label(): string
    {
        return 'Карты';
    }

    public function singular(): string
    {
        return 'карта';
    }

    public function model(): string
    {
        return Card::class;
    }

    public function icon(): string
    {
        return 'card';
    }

    public function exchangeEntity(): ?string
    {
        return 'cards';
    }

    /** A46 · Где у записи страна — для фильтра в плашке админки и стран сотрудника. */
    public function countryScope(): ?string
    {
        return 'bank.country_id';
    }

    public function query(): Builder
    {
        return Card::query()->with(['bank.country', 'paymentSystems', 'tiers']);
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
            'kind' => ['label' => 'Вид', 'options' => collect(CardKind::cases())->mapWithKeys(fn ($k) => [$k->value => $k->label()])->all(), 'apply' => fn ($q, $v) => $q->where('kind', $v)],
            'system' => ['label' => 'Система', 'options' => fn () => PaymentSystem::query()->ordered()->pluck('name', 'id')->all(), 'apply' => fn ($q, $v) => $q->whereHas('paymentSystems', fn ($p) => $p->whereKey($v))],
            'active' => ['label' => 'На сайте', 'options' => ['1' => 'Активные', '0' => 'Скрытые'], 'apply' => fn ($q, $v) => $q->where('is_active', $v === '1')],
            'gaps' => ['label' => 'Пробелы', 'options' => ['networks' => 'Без платёжной системы', 'fee' => 'Не указано обслуживание'], 'apply' => fn ($q, $v) => $v === 'networks'
                ? $q->doesntHave('paymentSystems')
                : $q->whereNull('annual_fee')->whereNull('monthly_fee')],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Карта', 'sort' => 'name', 'render' => fn (Card $c) => self::html(
                '<span class="flex items-center gap-3"><span class="mini-card" style="background: '.e($c->face()).'"></span><span><b>'.e($c->name).'</b><span class="faint text-xs block">'.($c->bank?->country?->flagImg() ?? '').' '.e($c->bank?->displayName()).' · <span class="font-mono">'.e($c->slug).'</span></span></span></span>')],
            ['key' => 'kind', 'label' => 'Вид', 'sort' => 'kind', 'render' => fn (Card $c) => $c->kind?->label()],
            ['key' => 'networks', 'label' => 'Системы', 'render' => fn (Card $c) => self::html($c->networks()->map(fn ($n) => '<span class="chip !py-0.5">'.e($n['tier']?->name ?? $n['system']->displayName()).'</span>')->implode(' ') ?: '<span class="text-[var(--warn)] text-sm">не указаны</span>')],
            ['key' => 'annual_fee', 'label' => 'В год', 'sort' => 'annual_fee', 'render' => fn (Card $c) => self::html('<span class="tabular">'.($c->yearlyFee() === null ? '<span class="faint">—</span>' : ($c->yearlyFee() == 0 ? 'бесплатно' : e(money_format($c->yearlyFee(), currency_symbol($c->bank?->country?->currency))))).'</span>')],
            ['key' => 'cashback_max', 'label' => 'Кэшбэк', 'sort' => 'cashback_max', 'render' => fn (Card $c) => self::html('<span class="tabular">'.($c->cashback_max !== null ? 'до '.percent_format($c->cashback_max) : '<span class="faint">—</span>').'</span>')],
            ['key' => 'is_active', 'label' => 'На сайте', 'sort' => 'is_active', 'render' => fn (Card $c) => self::yesNo($c->is_active)],
        ];
    }

    /** Опции сетей: «psId:tierId» (0 — уровень не указан) → «Visa|Visa Gold». */
    public static function networkOptions(): array
    {
        $out = [];
        foreach (PaymentSystem::query()->ordered()->with('tiers')->get() as $ps) {
            $out[$ps->id.':0'] = $ps->name.'|'.$ps->name.' (без уровня)';
            foreach ($ps->tiers as $t) {
                $out[$ps->id.':'.$t->id] = $ps->name.'|'.$t->name;
            }
        }

        return $out;
    }

    public function fields(?Model $model = null): array
    {
        $money = fn ($name, $label, $group, $help = null) => ['name' => $name, 'label' => $label, 'type' => 'decimal', 'group' => $group, 'help' => $help, 'rules' => ['nullable', 'numeric', 'min:0']];
        $pct = fn ($name, $label, $group, $help = null) => ['name' => $name, 'label' => $label, 'type' => 'decimal', 'group' => $group, 'help' => $help, 'rules' => ['nullable', 'numeric', 'min:0', 'max:200']];

        return [
            ['name' => 'bank_id', 'label' => 'Банк', 'type' => 'select', 'span' => 2, 'rules' => ['required', 'integer', 'exists:banks,id'],
                'options' => fn () => Bank::query()->with('country')->tap(fn ($q) => app(AdminScope::class)->apply($q, 'country_id', selection: false))->orderBy('country_id')->orderBy('name')->get()->mapWithKeys(fn ($b) => [$b->id => trim(($b->country?->flag ?? '').' '.$b->displayName().' · '.$b->code)])->all()],
            ['name' => 'name', 'label' => 'Название', 'rules' => ['required', 'string', 'max:160']],
            ['name' => 'kind', 'label' => 'Вид', 'type' => 'select', 'rules' => ['required'], 'options' => collect(CardKind::cases())->mapWithKeys(fn ($k) => [$k->value => $k->label().' — '.mb_strtolower($k->hint())])->all()],
            ['name' => 'slug', 'label' => 'Адрес на сайте', 'help' => 'пусто — из банка и названия', 'rules' => ['nullable', 'alpha_dash', 'max:160', Rule::unique('cards')->ignore($model)]],
            ['name' => 'segment', 'label' => 'Сегмент', 'type' => 'select', 'options' => Card::SEGMENTS],
            ['name' => 'format', 'label' => 'Формат', 'type' => 'select', 'rules' => ['required'], 'options' => Card::FORMATS, 'default' => 'physical'],
            ['name' => 'currencies', 'label' => 'Валюты', 'type' => 'list', 'help' => 'по одной в строке', 'placeholder' => "KZT\nUSD"],
            ['name' => 'is_active', 'label' => 'Показывать на сайте', 'type' => 'bool', 'default' => true],
            ['name' => 'online_opening', 'label' => 'Оформить онлайн', 'type' => 'bool', 'default' => true],
            ['name' => 'delivery', 'label' => 'Доставка курьером', 'type' => 'bool'],
            ['name' => 'networks', 'label' => 'Платёжные системы и уровни', 'type' => 'checks', 'virtual' => true, 'group' => 'Платёжные системы', 'options' => fn () => self::networkOptions(),
                'value' => fn (Card $c) => DB::table('card_networks')->where('card_id', $c->id)->get()->map(fn ($r) => $r->payment_system_id.':'.($r->card_tier_id ?? 0))->all(),
                'help' => 'отметьте все системы, в которых выпускается карта; уровень — самый точный из известных'],

            $money('issue_fee', 'Выпуск', 'Обслуживание'),
            $money('annual_fee', 'Обслуживание в год', 'Обслуживание', '0 — бесплатно'),
            $money('monthly_fee', 'Обслуживание в месяц', 'Обслуживание', 'если банк берёт помесячно'),
            ['name' => 'fee_note', 'label' => 'Когда бесплатно', 'group' => 'Обслуживание', 'rules' => ['nullable', 'string', 'max:255'], 'placeholder' => 'при тратах от 100 000 ₸ в месяц'],

            $pct('cashback_max', 'Кэшбэк до, %', 'Кэшбэк и остаток'),
            $pct('cashback_base', 'Кэшбэк на всё, %', 'Кэшбэк и остаток'),
            ['name' => 'cashback_note', 'label' => 'Категории и лимиты кэшбэка', 'group' => 'Кэшбэк и остаток', 'span' => 2, 'rules' => ['nullable', 'string', 'max:255']],
            $pct('balance_rate', 'Процент на остаток, %', 'Кэшбэк и остаток'),

            $money('credit_limit_max', 'Кредитный лимит до', 'Кредит'),
            ['name' => 'grace_days', 'label' => 'Льготный период, дней', 'type' => 'number', 'group' => 'Кредит', 'rules' => ['nullable', 'integer', 'min:0', 'max:400']],
            $pct('credit_rate', 'Ставка, % годовых', 'Кредит'),
            $pct('credit_rate_effective', 'ГЭСВ, %', 'Кредит'),
            $pct('min_payment', 'Минимальный платёж, % долга', 'Кредит'),

            ['name' => 'cash_fee', 'label' => 'Снятие наличных', 'group' => 'Снятие и переводы', 'rules' => ['nullable', 'string', 'max:255']],
            $money('free_cash_limit', 'Бесплатное снятие в месяц', 'Снятие и переводы'),
            ['name' => 'transfer_fee', 'label' => 'Переводы', 'group' => 'Снятие и переводы', 'span' => 2, 'rules' => ['nullable', 'string', 'max:255']],

            ['name' => 'features', 'label' => 'Особенности', 'type' => 'checks', 'group' => 'Выпуск и оформление', 'options' => config('banq.cards.features')],
            ['name' => 'validity_years', 'label' => 'Срок действия, лет', 'type' => 'number', 'group' => 'Выпуск и оформление', 'rules' => ['nullable', 'integer', 'min:1', 'max:15']],
            ['name' => 'age_from', 'label' => 'Возраст от', 'type' => 'number', 'group' => 'Выпуск и оформление', 'rules' => ['nullable', 'integer', 'min:0', 'max:100']],
            ['name' => 'color', 'label' => 'Цвет карты', 'type' => 'color', 'group' => 'Выпуск и оформление', 'help' => 'пусто — цвет банка', 'rules' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']],
            ['name' => 'color_2', 'label' => 'Второй цвет градиента', 'type' => 'color', 'group' => 'Выпуск и оформление', 'rules' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']],
            ['name' => 'image', 'label' => 'Изображение (путь или URL)', 'group' => 'Выпуск и оформление', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'sort', 'label' => 'Порядок', 'type' => 'number', 'group' => 'Выпуск и оформление', 'rules' => ['nullable', 'integer', 'min:0'], 'default' => 500],
            ['name' => 'source_url', 'label' => 'Страница на сайте банка', 'type' => 'url', 'group' => 'Выпуск и оформление', 'span' => 2, 'rules' => ['nullable', 'url', 'max:255']],
            ['name' => 'description', 'label' => 'Описание', 'type' => 'textarea', 'group' => 'Выпуск и оформление', 'rules' => ['nullable', 'string', 'max:10000']],
            ['name' => 'conditions', 'label' => 'Прочие условия (JSON)', 'type' => 'json', 'group' => 'Выпуск и оформление', 'help' => '{"смс-информирование": "300 ₸ в месяц"}'],
        ];
    }

    public function defaults(Request $request): array
    {
        $bank = Bank::find($request->query('bank'));

        return ['bank_id' => $bank?->id, 'currencies' => [$bank?->country?->currency ?? country()?->currency ?? 'KZT'], 'kind' => $request->query('kind', 'debit')];
    }

    public function saving(Model $m, array $data, Request $request): void
    {
        $m->sort ??= 500;
        if (! $m->slug) {
            $base = Str::slug(Bank::find($m->bank_id)?->slug.'-'.$m->name);
            for ($i = 1, $s = $base; Card::query()->where('slug', $s)->whereKeyNot($m->id)->exists(); $s = $base.'-'.++$i);
            $m->slug = $s;
        }
    }

    public function saved(Model $m, array $data, Request $request): void
    {
        $rows = collect((array) $request->input('networks', []))->filter(fn ($v) => preg_match('/^\d+:\d+$/', (string) $v))->unique()
            ->map(fn ($v) => explode(':', $v))->map(fn ($p) => ['card_id' => $m->id, 'payment_system_id' => (int) $p[0], 'card_tier_id' => (int) $p[1] ?: null])->values();
        DB::table('card_networks')->where('card_id', $m->id)->delete();
        DB::table('card_networks')->insert($rows->all());
    }

    public function bulkActions(): array
    {
        return [
            'activate' => ['label' => 'Показать на сайте', 'run' => fn (Collection $m) => 'Показано: '.$m->each->update(['is_active' => true])->count()],
            'deactivate' => ['label' => 'Скрыть', 'run' => fn (Collection $m) => 'Скрыто: '.$m->each->update(['is_active' => false])->count()],
            ...parent::bulkActions(),
        ];
    }

    public function title(Model $m): string
    {
        return $m->name;
    }

    public function publicUrl(Model $m): ?string
    {
        return $m->is_active ? route('cards.show', $m) : null;
    }

    public function summary(): array
    {
        return ['Всего' => Card::count(), 'Дебетовых' => Card::where('kind', 'debit')->count(), 'Кредитных' => Card::where('kind', 'credit')->count(), 'Без системы' => Card::doesntHave('paymentSystems')->count()];
    }
}
