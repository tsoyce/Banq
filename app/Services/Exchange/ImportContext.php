<?php

namespace App\Services\Exchange;

use App\Models\Country;

/** A22 · Состояние прогона: параметры, отчёт, страна по умолчанию, id импорта (для deposit_rates.import_id). */
final class ImportContext
{
    private ?Country $defaultCountry = null;

    private bool $countryResolved = false;

    public ?int $importId = null;

    public function __construct(public readonly ImportOptions $options, public readonly ImportReport $report = new ImportReport) {}

    /** Страна для строки: поле country → --country → настройка country.default → первая страна. */
    public function country(?string $value): Country
    {
        if ($value !== null && $value !== '') {
            return Country::findByIdentifier($value) ?? throw new RowError("страна «{$value}» не найдена — добавь её в countries");
        }
        if (! $this->countryResolved) {
            $this->defaultCountry = Country::findByIdentifier($this->options->country)
                ?? Country::findByIdentifier((string) settings('country.default', ''))
                ?? Country::query()->orderBy('sort')->first();
            $this->countryResolved = true;
        }

        return $this->defaultCountry ?? throw new RowError('нет ни одной страны — сначала импортируй countries');
    }
}
