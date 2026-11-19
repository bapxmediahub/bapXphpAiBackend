<?php
namespace App\Services;

final class ProductImportService
{
    public const MAX_BYTES = 65536;
    public const MAX_ROWS = 100;
    private const REQUIRED = ['slug', 'name', 'price'];
    private const OPTIONAL = ['category', 'description'];

    /** Parse and validate the whole file before either preview or commit. */
    public static function parse(string $csv): array
    {
        if ($csv === '' || strlen($csv) > self::MAX_BYTES || !mb_check_encoding($csv, 'UTF-8')) {
            throw new \InvalidArgumentException('Choose a UTF-8 CSV file up to 64 KiB.');
        }
        $csv = preg_replace('/^\x{feff}/u', '', $csv);
        $file = fopen('php://memory', 'w+');
        fwrite($file, $csv);
        rewind($file);
        $columns = null;
        $records = [];
        $number = 0;
        try {
            while (!feof($file)) {
                $start = ftell($file);
                $cells = fgetcsv($file, 0, ',', '"', '');
                if ($cells === false) break;
                $number++;
                if ($cells === [null]) continue;
                $raw = substr($csv, $start, ftell($file) - $start);
                $field = '(?:"(?:[^"]++|"")*"|[^",\r\n]*+)';
                if (!preg_match('/^' . $field . '(?:,' . $field . ')*+(?:\r\n|\n|\r)?\z/', $raw)) {
                    throw new \InvalidArgumentException("Row $number contains an invalid quoted field.");
                }
                if ($columns === null) {
                    $columns = array_map('trim', $cells);
                    if (array_diff(self::REQUIRED, $columns) || array_diff($columns, [...self::REQUIRED, ...self::OPTIONAL])
                        || count(array_unique($columns)) !== count($columns)) {
                        throw new \InvalidArgumentException('Include slug, name and price once each. Optional columns are category and description.');
                    }
                    continue;
                }
                if (count($records) >= self::MAX_ROWS) throw new \InvalidArgumentException('Split files into batches of up to 100 products.');
                if (count($cells) !== count($columns)) throw new \InvalidArgumentException("Row $number has the wrong number of columns.");
                $row = array_combine($columns, array_map('trim', $cells));
                $slug = $row['slug'];
                if (strlen($slug) > 120 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
                    throw new \InvalidArgumentException("Row $number needs a lowercase slug made of letters, digits and hyphens.");
                }
                if (isset($records[$slug])) throw new \InvalidArgumentException("Row $number repeats a slug in this file.");
                if ($row['name'] === '' || mb_strlen($row['name']) > 200 || mb_strlen($row['category'] ?? '') > 120
                    || mb_strlen($row['description'] ?? '') > 5000) {
                    throw new \InvalidArgumentException("Row $number needs a name of up to 200 characters, category up to 120 and description up to 5,000.");
                }
                foreach ($row as $value) {
                    if (preg_match('/^[=+@-]|[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
                        throw new \InvalidArgumentException("Row $number contains a formula prefix or control character.");
                    }
                }
                if (!preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/D', $row['price']) || (float)$row['price'] <= 0) {
                    throw new \InvalidArgumentException("Row $number needs a positive INR price with at most eight digits and two decimal places.");
                }
                $row['price'] = (float)$row['price'];
                $records[$slug] = $row + ['category'=>'', 'description'=>'', 'status'=>'hidden', 'stock_status'=>'out_of_stock'];
            }
        } finally {
            fclose($file);
        }
        if (!$records) throw new \InvalidArgumentException('Add at least one product below the header.');
        return array_values($records);
    }
}
