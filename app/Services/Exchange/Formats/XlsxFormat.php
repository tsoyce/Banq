<?php

namespace App\Services\Exchange\Formats;

use App\Services\Exchange\EntityRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** A22 · XLSX/ODS/XLS: лист = сущность (имя листа — ключ или название: banks / «Банки»). Первая строка — заголовки. */
class XlsxFormat implements Format
{
    use Tabular;

    public function key(): string
    {
        return 'xlsx';
    }

    public function extension(): string
    {
        return 'xlsx';
    }

    public function mime(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function read(string $content, ?string $entity = null): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'banq');
        file_put_contents($tmp, $content);
        try {
            $book = IOFactory::load($tmp);
        } finally {
            @unlink($tmp);
        }

        $out = [];
        $registry = app(EntityRegistry::class);
        foreach ($book->getWorksheetIterator() as $sheet) {
            $key = $registry->find($sheet->getTitle())?->key() ?? ($book->getSheetCount() === 1 ? $entity : null);
            if ($key === null) {
                continue; // лист-черновик без понятного имени
            }
            $rows = $this->rowsFromTable($sheet->toArray(null, true, false, false));
            $out[$key] = [...($out[$key] ?? []), ...$rows];
        }
        if (! $out) {
            throw new \InvalidArgumentException('Ни один лист не назван по сущности (countries, banks, deposit_products, deposit_rates) — переименуй листы или укажи entity');
        }

        return $out;
    }

    public function write(array $bundle): string
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach ($bundle as $entity => $rows) {
            [$headers, $data] = $this->flatten($rows);
            $sheet = $book->createSheet()->setTitle(substr($entity, 0, 31));
            $sheet->fromArray([$headers, ...$data], null, 'A1', true);
            $sheet->freezePane('A2');
            $sheet->getStyle('1:1')->getFont()->setBold(true);
            foreach (range(1, max(1, count($headers))) as $i) {
                $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            }
        }
        if ($book->getSheetCount() === 0) {
            $book->createSheet()->setTitle('empty');
        }

        ob_start();
        (new Xlsx($book))->save('php://output');

        return ob_get_clean();
    }
}
