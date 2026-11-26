<?php

namespace App\Models;

use App\Enums\RatingKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** A18 · Оценка банка за дату. Авторская — из админки, объективная и народная — снимки RatingSnapshot. */
class Rating extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'kind' => RatingKind::class,
        'score' => 'decimal:2',
        'breakdown' => 'array',
        'pros' => 'array',
        'cons' => 'array',
        'period' => 'date',
    ];

    /** Дата хранится как Y-m-d, чтобы уникальный ключ bank+kind+period совпадал в MySQL и SQLite. */
    public function setPeriodAttribute(mixed $value): void
    {
        $this->attributes['period'] = Carbon::parse($value)->toDateString();
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
