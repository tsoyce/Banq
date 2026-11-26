<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Countries;
use App\Support\Username;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'avatar', 'city', 'country_code', 'bio', 'is_public', 'notify'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** A28 · Уведомления на почту и значения по умолчанию. */
    public const NOTIFY = [
        'review_status' => ['Решение модератора', 'Когда отзыв опубликован или отклонён — с причиной', true],
        'review_reply' => ['Ответ банка на отзыв', 'Когда представитель банка ответил вам', true],
        'rate_changes' => ['Ставки в избранном', 'Когда меняется ставка по избранным вкладам', false],
    ];

    /** A28 · Ступени автора в народном рейтинге: [мин. опубликованных отзывов, мин. «полезно», подпись]. */
    public const LEVELS = [
        [10, 50, 'Эксперт'],
        [5, 10, 'Знаток'],
        [2, 0, 'Автор'],
        [1, 0, 'Первый отзыв'],
        [0, 0, 'Читатель'],
    ];

    /** Набор «тип:id» избранного — один запрос на страницу. */
    private ?array $favoriteKeys = null;

    protected static function booted(): void
    {
        // A28 · Каждому — адрес публичного профиля
        static::creating(function (User $user) {
            $user->username ??= Username::generate($user->name ?: 'user');
        });
    }

    /** A18 · Отзывы пользователя о банках. */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** A28 · Избранное (вклады и банки). */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /** A28 · Сохранённые сравнения вкладов. */
    public function comparisons(): HasMany
    {
        return $this->hasMany(SavedComparison::class);
    }

    /** A18 · Сотрудник портала (админка, модерация). */
    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'editor'], true);
    }

    /** A28 · В избранном ли вклад/банк. */
    public function hasFavorite(Model $model): bool
    {
        $this->favoriteKeys ??= $this->favorites()->get(['favoritable_type', 'favoritable_id'])
            ->map(fn ($f) => $f->favoritable_type.':'.$f->favoritable_id)->flip()->all();

        return isset($this->favoriteKeys[$model->getMorphClass().':'.$model->getKey()]);
    }

    public function forgetFavorites(): void
    {
        $this->favoriteKeys = null;
    }

    /** A28 · Включено ли уведомление (с учётом значений по умолчанию). */
    public function wants(string $key): bool
    {
        return (bool) (($this->notify ?? [])[$key] ?? (self::NOTIFY[$key][2] ?? false));
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar ? asset($this->avatar) : null;
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr(trim($this->name) ?: '?', 0, 1));
    }

    /** Оттенок градиента аватара-заглушки — стабильный для пользователя. */
    public function hue(): int
    {
        return (crc32((string) $this->id.$this->username) % 360 + 360) % 360;
    }

    /** «ноября 2026» — родительный падеж для «с …». */
    public function memberSince(): string
    {
        return $this->created_at ? preg_replace('/^\d+\s+/u', '', $this->created_at->locale('ru')->isoFormat('D MMMM YYYY')) : '';
    }

    public function countryName(): ?string
    {
        return Countries::name($this->country_code);
    }

    public function location(): ?string
    {
        return collect([$this->city, $this->countryName()])->filter()->join(', ') ?: null;
    }

    public function profileUrl(): ?string
    {
        return $this->is_public && $this->username ? route('profile.show', $this->username) : null;
    }

    /**
     * A28 · Ступень автора по опубликованным отзывам и «полезно».
     *
     * @return array{label: string, next: ?string, need: ?int}
     */
    public function level(int $reviews, int $helpful): array
    {
        foreach (self::LEVELS as $i => [$minReviews, $minHelpful, $label]) {
            if ($reviews >= $minReviews && $helpful >= $minHelpful) {
                $next = self::LEVELS[$i - 1] ?? null;

                return ['label' => $label, 'next' => $next[2] ?? null, 'need' => $next ? max(0, $next[0] - $reviews) : null];
            }
        }

        return ['label' => 'Читатель', 'next' => null, 'need' => null];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'is_public' => 'boolean',
            'notify' => 'array',
        ];
    }
}
