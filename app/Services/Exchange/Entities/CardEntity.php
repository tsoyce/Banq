<?php

namespace App\Services\Exchange\Entities;

use App\Enums\CardAudience;
use App\Enums\CardKind;
use App\Models\Bank;
use App\Models\Card;
use App\Models\CardTier;
use App\Models\PaymentSystem;
use App\Services\Exchange\Concerns\ResolvesBank;
use App\Services\Exchange\Entity;
use App\Services\Exchange\ImportContext;
use App\Services\Exchange\RowError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A31 · Банковские карты: ключ — slug, иначе банк + название + вид.
 * networks — список «СИСТЕМА:уровень» («VISA:gold», «Mastercard World Elite», «UnionPay») → card_networks.
 */
class CardEntity extends Entity
{
    use ResolvesBank;

    public function key(): string
    {
        return 'cards';
    }

    public function label(): string
    {
        return 'Карты';
    }

    public function description(): string
    {
        return 'Банковские карты: дебетовые, кредитные, предоплаченные. Ключ — slug; если его нет — bank (+country) + name (+kind). '
            .'networks — платёжные системы и уровни: «VISA:gold», «Mastercard World Elite», «UnionPay». Суммы — в валюте страны банка.';
    }

    public function aliases(): array
    {
        return ['card', 'карты', 'банковские карты'];
    }

    public function relations(): array
    {
        return ['networks'];
    }

    public function fields(): array
    {
        $features = implode(', ', array_keys(config('banq.cards.features')));

        return [
            'country' => ['ref', 'desc' => 'код страны банка (по умолчанию — страна импорта)', 'example' => 'KZ', 'aliases' => ['страна']],
            'bank' => ['ref', 'desc' => 'код, имя или алиас банка', 'example' => 'BCC', 'aliases' => ['банк', 'bank_code']],
            'slug' => ['string', 'desc' => 'ключ и URL; по умолчанию банк + название', 'example' => 'bcc-cashback-card', 'rules' => 'max:160|alpha_dash'],
            'name' => ['string', 'desc' => 'название карты (обязательно для новой)', 'example' => '#картакарта', 'rules' => 'max:160', 'aliases' => ['название', 'карта']],
            'kind' => ['enum', 'values' => array_map(fn ($k) => $k->value, CardKind::cases()), 'parse' => fn ($s) => CardKind::fromLabel($s)?->value,
                'desc' => 'debit — дебетовая, credit — кредитная, prepaid — предоплаченная; понимает русские подписи', 'example' => 'debit', 'aliases' => ['вид', 'тип']],
            'networks' => ['list', 'desc' => 'платёжные системы и уровни: «VISA:gold», «Mastercard World Elite»', 'example' => ['VISA:gold', 'MASTERCARD:world'], 'aliases' => ['системы', 'платёжные системы']],
            'audience' => ['enum', 'values' => array_map(fn ($a) => $a->value, CardAudience::cases()), 'parse' => fn ($s) => CardAudience::fromLabel($s)?->value, // A54
                'desc' => 'personal — физлицам, business — бизнесу (юрлица, ИП); понимает «физлица», «юрлица», «ИП», «бизнес». Пусто у новой — по уровню (Visa Business → business)', 'example' => 'personal', 'aliases' => ['для кого', 'клиенты', 'аудитория']],
            'segment' => ['enum', 'values' => array_keys(Card::SEGMENTS), 'example' => 'mass', 'aliases' => ['сегмент'],
                'parse' => fn ($s) => CardAudience::fromLabel($s) === CardAudience::Business ? 'business' : null], // A54: старое «business» → audience
            'format' => ['enum', 'values' => array_keys(Card::FORMATS), 'desc' => 'physical — пластик, virtual — виртуальная, both — обе', 'example' => 'both'],
            'currencies' => ['list', 'example' => ['KZT', 'USD'], 'aliases' => ['валюты']],
            'issue_fee' => ['decimal', 'desc' => 'выпуск', 'example' => 0, 'aliases' => ['выпуск']],
            'annual_fee' => ['decimal', 'desc' => 'обслуживание в год', 'example' => 0, 'aliases' => ['обслуживание в год']],
            'monthly_fee' => ['decimal', 'desc' => 'обслуживание в месяц', 'aliases' => ['обслуживание в месяц']],
            'fee_note' => ['string', 'desc' => 'условие бесплатного обслуживания', 'rules' => 'max:255'],
            'cashback_max' => ['decimal', 'desc' => 'кэшбэк до, %', 'example' => 5, 'aliases' => ['кэшбэк', 'cashback']],
            'cashback_base' => ['decimal', 'desc' => 'кэшбэк на всё, %', 'example' => 1],
            'cashback_note' => ['string', 'desc' => 'категории, лимиты', 'rules' => 'max:255'],
            'balance_rate' => ['decimal', 'desc' => 'процент на остаток, % годовых', 'aliases' => ['на остаток']],
            'credit_limit_max' => ['decimal', 'desc' => 'кредитный лимит до', 'aliases' => ['лимит']],
            'grace_days' => ['int', 'desc' => 'льготный период, дней', 'example' => 55, 'rules' => 'integer|min:0|max:400', 'aliases' => ['льготный период']],
            'credit_rate' => ['decimal', 'desc' => 'ставка, % годовых', 'aliases' => ['ставка']],
            'credit_rate_effective' => ['decimal', 'desc' => 'ГЭСВ, %', 'aliases' => ['гэсв']],
            'min_payment' => ['decimal', 'desc' => 'минимальный платёж, % долга'],
            'cash_fee' => ['string', 'desc' => 'снятие наличных', 'rules' => 'max:255'],
            'free_cash_limit' => ['decimal', 'desc' => 'бесплатное снятие в месяц'],
            'transfer_fee' => ['string', 'rules' => 'max:255'],
            'online_opening' => ['bool', 'example' => true, 'aliases' => ['онлайн']],
            'delivery' => ['bool', 'desc' => 'доставка курьером', 'aliases' => ['доставка']],
            'validity_years' => ['int', 'rules' => 'integer|min:1|max:15'],
            'age_from' => ['int', 'rules' => 'integer|min:0|max:100'],
            'features' => ['list', 'desc' => 'особенности: '.$features, 'example' => ['contactless', 'apple_pay', 'google_pay']],
            'color' => ['string', 'desc' => 'цвет лицевой стороны', 'rules' => 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_2' => ['string', 'rules' => 'regex:/^#[0-9a-fA-F]{6}$/'],
            'image' => ['string', 'rules' => 'max:255'],
            'description' => ['text'],
            'conditions' => ['json', 'desc' => 'прочие условия ключ → значение'],
            'source_url' => ['string', 'rules' => 'url|max:255', 'aliases' => ['источник', 'url']],
            'is_active' => ['bool'],
            'sort' => ['int'],
        ];
    }

    public function find(array $row, ImportContext $ctx): ?Model
    {
        if (isset($row['slug']) && ($c = Card::query()->where('slug', $row['slug'])->first())) {
            return $c;
        }
        if (! isset($row['name'])) {
            return isset($row['slug']) ? null : throw new RowError('нужен slug или bank + name');
        }

        return Card::query()->where('bank_id', $this->bank($row, $ctx)->id)->where('name', $row['name'])
            ->when($row['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))->first();
    }

    public function attributes(array $row, ImportContext $ctx, ?Model $existing): array
    {
        $bank = isset($row['bank']) ? $this->bank($row, $ctx) : ($existing?->bank ?? throw new RowError('нужен bank'));
        unset($row['country'], $row['bank']);
        if (($row['segment'] ?? null) === 'business') { // A54 · совместимость: сегмент «для бизнеса» стал аудиторией
            $row['audience'] ??= CardAudience::Business->value;
            $row['segment'] = null;
        }
        if (! $existing) {
            $row['name'] ?? throw new RowError('для новой карты нужен name');
            $row['kind'] ?? throw new RowError('для новой карты нужен kind');
            $row['slug'] ??= self::uniqueSlugFor(Card::class, Str::slug($bank->slug.'-'.$row['name']));
            $row['currencies'] ??= [$bank->country?->currency ?? 'KZT'];
        }

        return ['bank_id' => $bank->id] + $row;
    }

    protected function syncRelations(Model $model, array $relations, ImportContext $ctx): array
    {
        $want = collect($relations['networks'] ?? [])->map(fn ($s) => self::network((string) $s))->unique(fn ($p) => $p[0]->id.':'.($p[1]?->id ?? 0))->values();
        $before = self::networkLabels($model);
        $after = $want->map(fn ($p) => $p[0]->code.($p[1] ? ':'.$p[1]->code : ''))->all();
        if ($before === $after) {
            return [];
        }
        DB::table('card_networks')->where('card_id', $model->getKey())->delete();
        DB::table('card_networks')->insert($want->map(fn ($p) => ['card_id' => $model->getKey(), 'payment_system_id' => $p[0]->id, 'card_tier_id' => $p[1]?->id])->all());
        if ($model->wasRecentlyCreated && $model->audience === CardAudience::Personal && $want->contains(fn ($p) => $p[1]?->isBusiness())) {
            $model->forceFill(['audience' => CardAudience::Business])->saveQuietly(); // A54 · новая карта на бизнес-уровне — для бизнеса
        }

        return ['networks' => [implode(' | ', $before) ?: null, implode(' | ', $after) ?: null]];
    }

    /**
     * «VISA:gold», «Visa Gold», «Mastercard World Elite», «UnionPay» → [система, уровень|null].
     *
     * @return array{0: PaymentSystem, 1: ?CardTier}
     */
    public static function network(string $s): array
    {
        $s = trim($s);
        if (str_contains($s, ':')) {
            [$sys, $tier] = array_map('trim', explode(':', $s, 2));
            $ps = PaymentSystem::findByIdentifier($sys) ?? throw new RowError("платёжная система «{$sys}» не найдена");
        } else {
            $n = Bank::normalizeKey($s);
            [$ps, $len] = [null, 0];
            foreach (PaymentSystem::query()->get() as $p) {
                foreach (array_filter([$p->code, $p->name, $p->short_name, ...($p->aliases ?? [])]) as $cand) {
                    $c = Bank::normalizeKey($cand);
                    if ($c !== '' && str_starts_with($n, $c) && strlen($c) > $len) {
                        [$ps, $len] = [$p, strlen($c)];
                    }
                }
            }
            $ps ?? throw new RowError("не понял платёжную систему в «{$s}» — пиши «VISA:gold»");
            $tier = substr($n, $len);
        }
        if ($tier === '' || $tier === null) {
            return [$ps, null];
        }

        return [$ps, CardTier::findFor($ps, $tier) ?? throw new RowError("у {$ps->displayName()} нет уровня «{$tier}» — добавь его в card_tiers")];
    }

    /** @return list<string> «VISA:gold» по порядку записей */
    public static function networkLabels(Model $card): array
    {
        return DB::table('card_networks')->where('card_id', $card->getKey())->orderBy('card_networks.id')
            ->join('payment_systems', 'payment_systems.id', '=', 'card_networks.payment_system_id')
            ->leftJoin('card_tiers', 'card_tiers.id', '=', 'card_networks.card_tier_id')
            ->get(['payment_systems.code as ps', 'card_tiers.code as tier'])
            ->map(fn ($r) => $r->ps.($r->tier ? ':'.$r->tier : ''))->all();
    }

    public function newModel(): Model
    {
        return new Card;
    }

    public function rowLabel(array $row): string
    {
        return $row['slug'] ?? trim(($row['bank'] ?? '').' · '.($row['name'] ?? '?'), ' ·');
    }

    public function exportQuery(array $filters): Builder
    {
        return Card::query()->with('bank.country')->orderBy('bank_id')->orderBy('sort')->orderBy('name')
            ->when($filters['country'] ?? null, fn ($q, $c) => $q->whereHas('bank.country', fn ($w) => $w->where('code', strtoupper($c))))
            ->when($filters['bank'] ?? null, fn ($q, $b) => $q->whereHas('bank', fn ($w) => $w->whereIn('code', array_map('strtoupper', (array) $b))));
    }

    public function toRow(Model $m): array
    {
        $row = ['country' => $m->bank?->country?->code, 'bank' => $m->bank?->code] + $this->exportFields($m, ['networks']);

        return array_slice($row, 0, 6, true) + ['networks' => self::networkLabels($m)] + array_slice($row, 6, null, true);
    }
}
