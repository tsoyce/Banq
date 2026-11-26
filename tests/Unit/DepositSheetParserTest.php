<?php

namespace Tests\Unit;

use App\Enums\DepositType;
use App\Services\Import\DepositSheetParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DepositSheetParserTest extends TestCase
{
    private function fixture(): string
    {
        return base_path('tests/fixtures/deposits-matrix.xlsx');
    }

    public function test_reads_main_sheet_with_rate_and_effective_rate_columns(): void
    {
        // «Лист2»: банки в строках, над каждым типом объединены 2 ячейки — ставка и ГЭСВ, формат 0.00%.
        $result = (new DepositSheetParser)->parse($this->fixture(), ['Лист2']);
        $by = collect($result['records'])->keyBy(fn ($r) => $r['bank'].'/'.$r['type']->value);

        $this->assertSame(['Лист2' => 27], $result['sheets']);
        $this->assertSame([14.2, 15.16], [$by['BCC/demand']['rate'], $by['BCC/demand']['effective_rate']]);
        $this->assertSame([16.67, 18.0], [$by['Kaspi/term']['rate'], $by['Kaspi/term']['effective_rate']]);
        $this->assertSame([16.5, 17.72], [$by['Alatau/savings']['rate'], $by['Alatau/savings']['effective_rate']]);
        $this->assertArrayNotHasKey('Kaspi/savings', $by->all()); // 0 = нет данных
        $this->assertArrayNotHasKey('Bereke/demand', $by->all());
    }

    public function test_reads_transposed_draft_sheet(): void
    {
        // «Sheet1»: банки в столбцах, типы — в строках.
        $records = (new DepositSheetParser)->parse($this->fixture(), ['Sheet1'])['records'];

        $this->assertCount(1, $records);
        $this->assertSame(['FFIN', DepositType::Demand, 16.35], [$records[0]['bank'], $records[0]['type'], $records[0]['rate']]);
    }

    public function test_conflicting_sheets_warn_and_last_sheet_wins(): void
    {
        $result = (new DepositSheetParser)->parse($this->fixture());
        $ffin = collect($result['records'])->first(fn ($r) => $r['bank'] === 'FFIN' && $r['type'] === DepositType::Demand);

        $this->assertSame(14.46, $ffin['rate']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_parses_flat_table_with_terms_and_percent_cells(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['Банк', 'Тип', 'Срок', 'Валюта', 'Ставка'],
            ['BCC', 'Пополнение', 12, 'KZT', '15,5%'],
            ['Halyk Bank', 'Табу', 6, 'usd', 1.2],
            ['FFIN', 'Табу', 6, 'KZT', 0],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'banq').'.xlsx';
        (new Xlsx($book))->save($path);

        $records = (new DepositSheetParser)->parse($path)['records'];
        @unlink($path);

        $this->assertCount(2, $records);
        $this->assertSame(['BCC', DepositType::Term, 15.5, 12, 'KZT'], [$records[0]['bank'], $records[0]['type'], $records[0]['rate'], $records[0]['term_months'], $records[0]['currency']]);
        $this->assertSame(['Halyk Bank', DepositType::Savings, 1.2, 'USD'], [$records[1]['bank'], $records[1]['type'], $records[1]['rate'], $records[1]['currency']]);
    }
}
