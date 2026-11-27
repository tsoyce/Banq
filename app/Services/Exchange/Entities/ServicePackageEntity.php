<?php

namespace App\Services\Exchange\Entities;

use App\Models\Card;
use App\Models\ServicePackage;
use App\Services\Exchange\Concerns\ResolvesBank;
use App\Services\Exchange\Entity;
use App\Services\Exchange\ImportContext;
use App\Services\Exchange\RowError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A31 · Пакеты услуг: ключ — slug, иначе банк + название. cards — slug или названия карт того же банка. */
class ServicePackageEntity extends Entity
{
    use ResolvesBank;

    public function key(): string
    {
        return 'service_packages';
    }

    public function label(): string
    {
        return 'Пакеты услуг';
    }

    public function description(): string
    {
        return 'Пакеты (тарифы) обслуживания банков. Ключ — slug; если его нет — bank (+country) + name. cards — slug или названия карт этого банка, которые входят в пакет.';
    }

    public function aliases(): array
    {
        return ['packages', 'пакеты', 'пакеты услуг', 'тарифы'];
    }

    public function relations(): array
    {
        return ['cards'];
    }

    public function fields(): array
    {
        return [
            'country' => ['ref', 'example' => 'KZ', 'aliases' => ['страна']],
            'bank' => ['ref', 'desc' => 'код, имя или алиас банка', 'example' => 'BCC', 'aliases' => ['банк']],
            'slug' => ['string', 'rules' => 'max:160|alpha_dash'],
            'name' => ['string', 'desc' => 'название (обязательно для нового)', 'example' => 'Premium', 'rules' => 'max:160', 'aliases' => ['название', 'пакет']],
            'segment' => ['enum', 'values' => array_keys(ServicePackage::SEGMENTS), 'example' => 'premium', 'aliases' => ['сегмент']],
            'monthly_fee' => ['decimal', 'desc' => 'стоимость в месяц', 'example' => 5000, 'aliases' => ['в месяц']],
            'annual_fee' => ['decimal', 'desc' => 'стоимость в год', 'aliases' => ['в год']],
            'fee_note' => ['string', 'desc' => 'когда бесплатно', 'rules' => 'max:255'],
            'perks' => ['list', 'desc' => 'что входит', 'example' => ['Премиальная карта', 'Бизнес-залы', 'Страховка']],
            'cards' => ['list', 'desc' => 'карты в пакете: slug или название', 'aliases' => ['карты']],
            'conditions' => ['json'],
            'description' => ['text'],
            'color' => ['string', 'rules' => 'regex:/^#[0-9a-fA-F]{6}$/'],
            'source_url' => ['string', 'rules' => 'url|max:255', 'aliases' => ['источник', 'url']],
            'is_active' => ['bool'],
            'sort' => ['int'],
        ];
    }

    public function find(array $row, ImportContext $ctx): ?Model
    {
        if (isset($row['slug']) && ($p = ServicePackage::query()->where('slug', $row['slug'])->first())) {
            return $p;
        }
        if (! isset($row['name'])) {
            return isset($row['slug']) ? null : throw new RowError('нужен slug или bank + name');
        }

        return ServicePackage::query()->where('bank_id', $this->bank($row, $ctx)->id)->where('name', $row['name'])->first();
    }

    public function attributes(array $row, ImportContext $ctx, ?Model $existing): array
    {
        $bank = isset($row['bank']) ? $this->bank($row, $ctx) : ($existing?->bank ?? throw new RowError('нужен bank'));
        unset($row['country'], $row['bank']);
        if (! $existing) {
            $row['name'] ?? throw new RowError('для нового пакета нужен name');
            $row['slug'] ??= self::uniqueSlugFor(ServicePackage::class, Str::slug($bank->slug.'-paket-'.$row['name']));
        }

        return ['bank_id' => $bank->id] + $row;
    }

    protected function syncRelations(Model $model, array $relations, ImportContext $ctx): array
    {
        $ids = collect($relations['cards'] ?? [])->map(function ($s) use ($model) {
            $card = Card::query()->where('slug', $s)->first() ?? Card::query()->where('bank_id', $model->bank_id)->where('name', $s)->first();

            return $card?->id ?? throw new RowError("карта «{$s}» не найдена у банка пакета");
        })->unique()->values()->all();
        $before = $model->cards()->orderBy('cards.slug')->pluck('slug')->all();
        $model->cards()->sync($ids);
        $after = $model->cards()->orderBy('cards.slug')->pluck('slug')->all();

        return $before === $after ? [] : ['cards' => [implode(' | ', $before) ?: null, implode(' | ', $after) ?: null]];
    }

    public function newModel(): Model
    {
        return new ServicePackage;
    }

    public function rowLabel(array $row): string
    {
        return $row['slug'] ?? trim(($row['bank'] ?? '').' · '.($row['name'] ?? '?'), ' ·');
    }

    public function exportQuery(array $filters): Builder
    {
        return ServicePackage::query()->with(['bank.country', 'cards'])->orderBy('bank_id')->orderBy('sort')->orderBy('name')
            ->when($filters['country'] ?? null, fn ($q, $c) => $q->whereHas('bank.country', fn ($w) => $w->where('code', strtoupper($c))))
            ->when($filters['bank'] ?? null, fn ($q, $b) => $q->whereHas('bank', fn ($w) => $w->whereIn('code', array_map('strtoupper', (array) $b))));
    }

    public function toRow(Model $m): array
    {
        return ['country' => $m->bank?->country?->code, 'bank' => $m->bank?->code] + $this->exportFields($m, ['cards'])
            + ['cards' => $m->cards->sortBy('slug')->pluck('slug')->values()->all()];
    }
}
