<?php

namespace App\Models;

use App\Enums\CardAudience;
use App\Enums\CardKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * A30 · Банковская карта (продукт): дебетовая / кредитная / предоплаченная.
 * Страна — через банк. Системы и уровни — card_networks (карта может выпускаться в нескольких).
 * Условия — текущий снимок; история изменений — в журнале импорта (imports.log, «было → стало»).
 */
class Card extends Model
{
    /** A54: «для бизнеса» — теперь не сегмент, а аудитория (audience), см. CardAudience. */
    public const SEGMENTS = ['mass' => 'Массовая', 'premium' => 'Премиальная', 'elite' => 'Элитная', 'youth' => 'Молодёжная', 'kids' => 'Детская'];

    public const FORMATS = ['physical' => 'Пластик', 'virtual' => 'Виртуальная', 'both' => 'Пластик и виртуальная'];

    protected $guarded = ['id'];

    protected $attributes = ['audience' => 'personal']; // A54

    protected $casts = [
        'kind' => CardKind::class,
        'audience' => CardAudience::class, // A54
        'currencies' => 'array', 'features' => 'array', 'conditions' => 'array',
        'online_opening' => 'boolean', 'delivery' => 'boolean', 'is_active' => 'boolean',
        'issue_fee' => 'decimal:2', 'annual_fee' => 'decimal:2', 'monthly_fee' => 'decimal:2',
        'cashback_max' => 'decimal:2', 'cashback_base' => 'decimal:2', 'balance_rate' => 'decimal:2',
        'credit_limit_max' => 'decimal:2', 'credit_rate' => 'decimal:2', 'credit_rate_effective' => 'decimal:2', 'min_payment' => 'decimal:2',
        'free_cash_limit' => 'decimal:2', 'grace_days' => 'integer', 'validity_years' => 'integer', 'age_from' => 'integer', 'sort' => 'integer',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /** Платёжные системы карты (pivot card_tier_id — уровень). */
    public function paymentSystems(): BelongsToMany
    {
        return $this->belongsToMany(PaymentSystem::class, 'card_networks')->withPivot('card_tier_id')->orderBy('sort');
    }

    public function tiers(): BelongsToMany
    {
        return $this->belongsToMany(CardTier::class, 'card_networks')->with('paymentSystem')->orderByDesc('level');
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(ServicePackage::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->whereHas('bank', fn ($b) => $b->where('status', 'active'));
    }

    /** Карты банков страны (null — текущая страна витрины). */
    public function scopeInCountry(Builder $q, Country|int|null $country = null): Builder
    {
        return $q->whereHas('bank', fn ($b) => $b->inCountry($country));
    }

    /**
     * Сети карты для показа: [{system, tier}] — по одной строке на систему, уровень — самый высокий.
     *
     * @return Collection<int, array{system: PaymentSystem, tier: ?CardTier}>
     */
    public function networks(): Collection
    {
        $this->loadMissing(['paymentSystems', 'tiers']);

        return $this->paymentSystems->unique('id')->map(fn (PaymentSystem $ps) => [
            'system' => $ps,
            'tier' => $this->tiers->where('payment_system_id', $ps->id)->sortByDesc('level')->first(),
        ])->values();
    }

    /** Самый высокий уровень карты (для сравнения): 0 — не указан. */
    public function topLevel(): int
    {
        $this->loadMissing('tiers');

        return (int) $this->tiers->max('level');
    }

    /** Обслуживание в год: годовая, иначе месячная × 12. null — не указано, 0 — бесплатно. */
    public function yearlyFee(): ?float
    {
        if ($this->annual_fee !== null) {
            return (float) $this->annual_fee;
        }

        return $this->monthly_fee !== null ? (float) $this->monthly_fee * 12 : null;
    }

    public function isFree(): bool
    {
        return $this->yearlyFee() === 0.0 && (float) $this->issue_fee === 0.0;
    }

    /** A33 · Стоимость в год словами: «бесплатно», «12 000 ₸/год», «2 990 ₸/мес». */
    public function feeLabel(): string
    {
        $sign = currency_symbol($this->bank?->country?->currency);

        return match (true) {
            $this->yearlyFee() === 0.0 => 'бесплатно',
            $this->monthly_fee !== null && $this->annual_fee === null => money_format((float) $this->monthly_fee, $sign).'/мес',
            $this->annual_fee !== null => money_format((float) $this->annual_fee, $sign).'/год',
            default => '—',
        };
    }

    /**
     * A33 · Три главные цифры карты для строки каталога: [подпись, значение, подсветить?].
     *
     * @return list<array{0: string, 1: string, 2: bool}>
     */
    public function keyFigures(): array
    {
        $pct = fn ($v) => $v === null ? '—' : rate_format($v);

        return match ($this->kind) {
            CardKind::Credit => [
                ['льготный период', $this->grace_days ? $this->grace_days.' дн.' : '—', (bool) $this->grace_days],
                ['ставка от', $pct($this->credit_rate), false],
                ['обслуживание', $this->feeLabel(), $this->yearlyFee() === 0.0],
            ],
            default => [
                ['кэшбэк до', $this->cashback_max === null ? '—' : percent_format($this->cashback_max), (float) $this->cashback_max > 0],
                ['обслуживание', $this->feeLabel(), $this->yearlyFee() === 0.0],
                $this->balance_rate !== null ? ['на остаток', $pct($this->balance_rate), false] : ['формат', mb_strtolower(self::FORMATS[$this->format] ?? '—'), false],
            ],
        };
    }

    /** A54 · Карта для бизнеса (юрлица, ИП). */
    public function isBusiness(): bool
    {
        return $this->audience === CardAudience::Business;
    }

    /** A54 · Аудитория по умолчанию: бизнес, если среди уровней есть бизнес-уровень (Visa Business…). */
    public function guessAudience(): CardAudience
    {
        $this->loadMissing('tiers');

        return $this->tiers->contains('segment', 'business') ? CardAudience::Business : CardAudience::Personal;
    }

    public function hasFeature(string $f): bool
    {
        return in_array($f, $this->features ?? [], true);
    }

    public function segmentLabel(): ?string
    {
        return $this->segment ? (self::SEGMENTS[$this->segment] ?? $this->segment) : null;
    }

    /** Лицевая сторона: свой градиент → цвет банка → фирменный. */
    public function face(): string
    {
        $a = $this->color ?: $this->bank?->brand_color ?: '#6d28d9';
        $b = $this->color_2 ?: ($this->color ? $a : '#0f172a');

        return 'linear-gradient(135deg, '.$a.' 0%, '.$b.' 100%)';
    }
}
