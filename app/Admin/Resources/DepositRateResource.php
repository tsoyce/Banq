<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\Country;
use App\Models\DepositProduct;
use App\Models\DepositRate;
use App\Support\AdminScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * A24 · Ставки: только список, добавление и закрытие — ставки не перезаписываются (правило 5).
 * Новая ставка идёт через движок обмена (DepositRateEntity): старая с тем же ключом закрывается.
 */
class DepositRateResource extends Resource
{
    public function key(): string
    {
        return 'rates';
    }

    public function label(): string
    {
        return 'Ставки';
    }

    public function singular(): string
    {
        return 'ставку';
    }

    public function model(): string
    {
        return DepositRate::class;
    }

    public function icon(): string
    {
        return 'trend';
    }

    public function description(): ?string
    {
        return 'История ставок. Ставку не правят — добавляют новую: она закроет прежнюю с тем же сроком, валютой и порогом суммы.';
    }

    public function exchangeEntity(): ?string
    {
        return 'deposit_rates';
    }

    /** A46 · Где у записи страна — для фильтра в плашке админки и стран сотрудника. */
    public function countryScope(): ?string
    {
        return 'product.bank.country_id';
    }

    public function query(): Builder
    {
        return DepositRate::query()->with('product.bank.country');
    }

    public function search(): array
    {
        return ['product.name', 'product.slug', 'currency'];
    }

    public function defaultSort(): array
    {
        return ['valid_from', 'desc'];
    }

    public function filters(): array
    {
        return [
            'state' => ['label' => 'Состояние', 'options' => ['current' => 'Действующие', 'closed' => 'История'], 'apply' => fn ($q, $v) => $v === 'current' ? $q->whereNull('valid_to') : $q->whereNotNull('valid_to')],
            'country' => ['label' => 'Страна', 'options' => fn () => app(AdminScope::class)->countryQuery()->pluck('name', 'id')->all(), 'apply' => fn ($q, $v) => $q->whereHas('product.bank', fn ($b) => $b->where('country_id', $v))],
            'currency' => ['label' => 'Валюта', 'options' => fn () => DepositRate::query()->distinct()->orderBy('currency')->pluck('currency', 'currency')->all(), 'apply' => fn ($q, $v) => $q->where('currency', $v)],
            'source' => ['label' => 'Источник', 'options' => ['manual' => 'Вручную', 'import' => 'Импорт', 'api' => 'API', 'parser' => 'Парсер'], 'apply' => fn ($q, $v) => $q->where('source', $v)],
            'product' => ['label' => 'Вклад (id)', 'apply' => fn ($q, $v) => $q->where('deposit_product_id', (int) $v)],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'product', 'label' => 'Вклад', 'render' => fn (DepositRate $r) => self::html('<a class="font-semibold hover:underline" href="'.route('admin.resource.edit', ['deposits', $r->deposit_product_id]).'">'.e($r->product?->name).'</a><p class="faint text-xs">'.($r->product?->bank?->country?->flagImg() ?? '').' '.e($r->product?->bank?->displayName()).'</p>')],
            ['key' => 'currency', 'label' => 'Валюта', 'sort' => 'currency', 'class' => 'font-mono text-sm'],
            ['key' => 'term_months', 'label' => 'Срок', 'sort' => 'term_months', 'render' => fn (DepositRate $r) => $r->term_months ? $r->term_months.' мес' : 'любой'],
            ['key' => 'amount_from', 'label' => 'Сумма от', 'sort' => 'amount_from', 'render' => fn (DepositRate $r) => $r->amount_from === null ? '—' : money_format($r->amount_from, $r->product?->bank?->country?->symbol())],
            ['key' => 'rate', 'label' => 'Ставка', 'sort' => 'rate', 'render' => fn (DepositRate $r) => self::html('<b class="tabular">'.rate_format($r->rate).'</b>')],
            ['key' => 'effective_rate', 'label' => 'ГЭСВ', 'sort' => 'effective_rate', 'render' => fn (DepositRate $r) => rate_format($r->effective_rate)],
            ['key' => 'valid_from', 'label' => 'Действует', 'sort' => 'valid_from', 'render' => fn (DepositRate $r) => self::html('<span class="text-sm tabular">'.$r->valid_from->format('d.m.Y').' → '.($r->valid_to ? $r->valid_to->format('d.m.Y') : '<span class="chip !py-0 !text-[var(--ok)]">сейчас</span>').'</span>')],
            ['key' => 'source', 'label' => 'Источник', 'sort' => 'source', 'class' => 'faint text-sm'],
        ];
    }

    public function fields(?Model $model = null): array
    {
        return [
            ['name' => 'deposit_product_id', 'label' => 'Вклад', 'type' => 'select', 'span' => 2, 'rules' => ['required', 'integer', 'exists:deposit_products,id'],
                'options' => fn () => DepositProduct::query()->with('bank')->orderBy('bank_id')->orderBy('name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->bank?->displayName().' — '.$p->name])->all()],
            ['name' => 'currency', 'label' => 'Валюта', 'rules' => ['nullable', 'alpha', 'size:3'], 'class' => 'font-mono uppercase', 'help' => 'пусто — валюта страны банка'],
            ['name' => 'term_months', 'label' => 'Срок, мес.', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0', 'max:600'], 'help' => 'пусто — любой срок'],
            ['name' => 'amount_from', 'label' => 'Сумма от', 'type' => 'decimal', 'rules' => ['nullable', 'numeric', 'min:0']],
            ['name' => 'amount_to', 'label' => 'Сумма до', 'type' => 'decimal', 'rules' => ['nullable', 'numeric', 'min:0']],
            ['name' => 'rate', 'label' => 'Ставка, %', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'min:0', 'max:500']],
            ['name' => 'effective_rate', 'label' => 'ГЭСВ, %', 'type' => 'decimal', 'rules' => ['nullable', 'numeric', 'min:0', 'max:500']],
            ['name' => 'valid_from', 'label' => 'Действует с', 'type' => 'date', 'rules' => ['nullable', 'date'], 'help' => 'пусто — сегодня'],
        ];
    }

    public function canEdit(Model $m): bool
    {
        return false;
    }

    public function defaults(Request $request): array
    {
        return ['deposit_product_id' => $request->query('product'), 'valid_from' => today()->toDateString()];
    }

    public function bulkActions(): array
    {
        return [
            'close' => ['label' => 'Закрыть сегодняшним днём', 'confirm' => 'Закрыть выбранные действующие ставки? История останется.',
                'run' => fn (Collection $m) => 'Закрыто: '.$m->whereNull('valid_to')->each->update(['valid_to' => today()])->count()],
            'delete' => ['label' => 'Удалить (ошибочные)', 'danger' => true, 'confirm' => 'Удалить записи из истории? Делай так только с ошибочными ставками.',
                'run' => fn (Collection $m) => 'Удалено: '.$m->each->delete()->count()],
        ];
    }

    public function title(Model $m): string
    {
        return ($m->product?->name ?? 'Ставка').' · '.rate_format($m->rate);
    }

    public function summary(): array
    {
        return ['Действующих' => DepositRate::current()->count(), 'В истории' => DepositRate::whereNotNull('valid_to')->count(), 'Добавлено за 7 дней' => DepositRate::where('created_at', '>=', now()->subWeek())->count()];
    }
}
