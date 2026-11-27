<?php

namespace App\Services\Exchange\Formats;

/** A22 · CSV: одна сущность на файл (или колонка _entity). Разделитель угадывается: , ; или табуляция. */
class CsvFormat implements Format
{
    use Tabular;

    public function key(): string
    {
        return 'csv';
    }

    public function extension(): string
    {
        return 'csv';
    }

    public function mime(): string
    {
        return 'text/csv';
    }

    public function read(string $content, ?string $entity = null): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $first = strtok($content, "\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($first, $d))->first();

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
        fputcsv($fh, $headers, ',', '"', '');
        foreach ($data as $r) {
            fputcsv($fh, $r, ',', '"', '');
        }
        rewind($fh);

        return stream_get_contents($fh);
    }
}
