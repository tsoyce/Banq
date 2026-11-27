<?php

namespace App\Services\Exchange\Concerns;

use App\Models\Bank;
use App\Services\Exchange\ImportContext;
use App\Services\Exchange\RowError;
use Illuminate\Support\Str;

/** A31 · Банк строки по полям bank (+country): код, имя или алиас внутри страны. */
trait ResolvesBank
{
    public function bank(array $row, ImportContext $ctx): Bank
    {
        $id = $row['bank'] ?? throw new RowError('нужен bank — код, имя или алиас банка');
        $country = $ctx->country($row['country'] ?? null);

        return Bank::query()->where('country_id', $country->id)->where('code', Str::upper($id))->first()
            ?? Bank::findByIdentifier($id, $country)
            ?? throw new RowError("банк «{$id}» не найден в стране {$country->code}");
    }

    /** Уникальный slug в таблице модели. */
    protected static function uniqueSlugFor(string $model, string $slug, ?int $ignore = null): string
    {
        $slug = $slug ?: 'item';
        for ($i = 1, $s = $slug; $model::query()->where('slug', $s)->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->exists(); $s = $slug.'-'.++$i);

        return $s;
    }
}
