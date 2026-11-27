<?php

namespace App\Services\Exchange\Formats;

/**
 * A22 · CSV: одна сущность на файл (или колонка _entity). Разделитель угадывается: , ; или табуляция.
 * A39 · С разделителем "\t" — формат TSV (так Excel и Google Таблицы кладут ячейки в буфер обмена).
 */
class CsvFormat implements Format
{
    use Tabular;

    public function __construct(private ?string $delimiter = null) {}

    private function tsv(): bool
    {
        return $this->delimiter === "\t";
    }

    public function key(): string
    {
        return $this->tsv() ? 'tsv' : 'csv';
    }

    public function extension(): string
    {
        return $this->key();
    }

    public function mime(): string
    {
        return $this->tsv() ? 'text/tab-separated-values' : 'text/csv';
    }

    public function read(string $content, ?string $entity = null): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $first = strtok($content, "\n") ?: '';
        $delimiter = $this->delimiter ?? collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($first, $d))->first();

        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $content);
        rewind($fh);
        $table = [];
        while (($r = fgetcsv($fh, null, $delimiter, '"', '')) !== false) {
            $table[] = $r;
        }
        fclose($fh);

        return $this->byEntity($this->rowsFromTable($table), $entity);
    }

    public function write(array $bundle): string
    {
        $multi = count($bundle) > 1;
        $rows = [];
        foreach ($bundle as $entity => $list) {
            foreach ($list as $r) {
                $rows[] = $multi ? ['_entity' => $entity] + $r : $r;
            }
        }
        [$headers, $data] = $this->flatten($rows);
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, "\xEF\xBB\xBF"); // BOM — чтобы Excel открыл кириллицу
        $d = $this->delimiter ?? ',';
        fputcsv($fh, $headers, $d, '"', '');
        foreach ($data as $r) {
            fputcsv($fh, $r, $d, '"', '');
        }
        rewind($fh);

        return stream_get_contents($fh);
    }
}
