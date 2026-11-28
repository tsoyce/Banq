<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\CardTier;
use App\Models\PaymentSystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A32 · Уровни карт платёжных систем: Visa Gold, Mastercard World Elite… */
class CardTierResource extends Resource
{
    public function key(): string
    {
        return 'card-tiers';
    }

    public function label(): string
    {
        return 'Уровни карт';
    }

    public function singular(): string
    {
        return 'уровень карты';
    }

    public function model(): string
    {
        return CardTier::class;
    }

    public function icon(): string
    {
        return 'award';
    }

    public function exchangeEntity(): ?string
    {
        return 'card_tiers';
    }

    public function query(): Builder
    {
        return CardTier::query()->with('paymentSystem')->withCount('cards');
    }

    public function search(): array
    {
        return ['name', 'code', 'slug', 'paymentSystem.name'];
    }

    public function defaultSort(): array
    {
        return ['level', 'desc'];
    }

    public function filters(): array
    {
        return [
            'system' => ['label' => 'Система', 'options' => fn () => PaymentSystem::query()->ordered()->pluck('name', 'id')->all(), 'apply' => fn ($q, $v) => $q->where('payment_system_id', $v)],
            'segment' => ['label' => 'Сегмент', 'options' => CardTier::SEGMENTS, 'apply' => fn ($q, $v) => $q->where('segment', $v)],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Уровень', 'sort' => 'name', 'render' => fn (CardTier $t) => self::html('<span class="flex items-center gap-3">'.view('partials.tier-badge', ['tier' => $t, 'c' => 'w-12'])->render().'<span><b>'.e($t->name).'</b><span class="faint text-xs block font-mono">'.e($t->slug).'</span></span></span>')], // A54: значок
            ['key' => 'system', 'label' => 'Система', 'render' => fn (CardTier $t) => $t->paymentSystem?->name],
            ['key' => 'level', 'label' => 'Ступень', 'sort' => 'level', 'render' => fn (CardTier $t) => self::html('<span class="level-dots" aria-label="'.$t->level.' из 6">'.str_repeat('<i class="on"></i>', $t->level).str_repeat('<i></i>', max(0, 6 - $t->level)).'</span>')],
            ['key' => 'segment', 'label' => 'Сегмент', 'sort' => 'segment', 'render' => fn (CardTier $t) => $t->segmentLabel()],
            ['key' => 'privileges', 'label' => 'Привилегий', 'render' => fn (CardTier $t) => self::html('<span class="tabular">'.count($t->privileges ?? []).'</span>')],
            ['key' => 'cards', 'label' => 'Карт', 'sort' => 'cards_count', 'render' => fn (CardTier $t) => self::html('<span class="tabular">'.$t->cards_count.'</span>')],
        ];
    }

    public function fields(?Model $model = null): array
    {
        return [
            ['name' => 'payment_system_id', 'label' => 'Платёжная система', 'type' => 'select', 'rules' => ['required', 'integer', 'exists:payment_systems,id'],
                'options' => fn () => PaymentSystem::query()->ordered()->pluck('name', 'id')->all()],
            ['name' => 'name', 'label' => 'Название', 'rules' => ['required', 'string', 'max:160'], 'placeholder' => 'Visa Infinite'],
            ['name' => 'code', 'label' => 'Код', 'help' => 'латиницей: gold, world_elite; пусто — из названия', 'rules' => ['nullable', 'string', 'max:48'], 'class' => 'font-mono'],
            ['name' => 'slug', 'label' => 'Адрес', 'help' => 'пусто — система + код', 'rules' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('card_tiers')->ignore($model)]],
            ['name' => 'level', 'label' => 'Ступень 1–6', 'type' => 'number', 'help' => '1 — базовая (Classic), 3 — Platinum, 5 — Infinite / World Elite', 'rules' => ['required', 'integer', 'min:1', 'max:6'], 'default' => 1],
            ['name' => 'segment', 'label' => 'Сегмент', 'type' => 'select', 'rules' => ['required'], 'options' => CardTier::SEGMENTS, 'default' => 'mass'],
            // A54 · значок уровня: свой файл или нейтральный рисунок по умолчанию (металл по ступени, подпись)
            ['name' => 'badge', 'label' => 'Значок уровня', 'type' => 'image', 'span' => 2, 'group' => 'Значок',
                'help' => 'SVG/PNG/WebP до 1 МБ; пусто — рисуем сами: «мини-карта» с металлом по ступени. Логотипы систем — только если есть право их использовать.'],
            ['name' => 'badge_text', 'label' => 'Подпись на значке', 'group' => 'Значок', 'rules' => ['nullable', 'string', 'max:60'],
                'help' => 'пусто — короткое имя («Gold»); можно латиницей или кириллицей: «MIR Privilege» / «МИР Привилегия»'],
            ['name' => 'meta.tone', 'label' => 'Металл значка', 'type' => 'select', 'group' => 'Значок', 'options' => CardTier::TONES, 'help' => 'пусто — по коду и ступени'],
            ['name' => 'privileges', 'label' => 'Привилегии', 'type' => 'list', 'span' => 2, 'help' => 'по одной в строке', 'placeholder' => "Консьерж-сервис\nБизнес-залы аэропортов"],
            ['name' => 'description', 'label' => 'Описание', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:5000']],
            ['name' => 'sort', 'label' => 'Порядок', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0'], 'default' => 500],
            ['name' => 'is_active', 'label' => 'Активен', 'type' => 'bool', 'default' => true],
        ];
    }

    public function fill(Model $m, array $data): void
    {
        parent::fill($m, $data);
        $m->sort ??= 500;
        if (! $m->code) {
            $m->code = $m->name;
        }
    }

    public function defaults(Request $request): array
    {
        return ['payment_system_id' => $request->query('system')];
    }

    public function publicUrl(Model $m): ?string
    {
        return $m->paymentSystem?->is_active ? route('payment-systems.show', $m->paymentSystem).'#tier-'.$m->code : null;
    }

    public function summary(): array
    {
        return ['Уровней' => CardTier::count(), 'Премиальных и элитных' => CardTier::whereIn('segment', ['premium', 'elite'])->count()];
    }
}
