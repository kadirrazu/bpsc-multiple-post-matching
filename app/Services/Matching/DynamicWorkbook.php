<?php

namespace App\Services\Matching;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class DynamicWorkbook
{
    public const REQUIRED = ['user', 'reg', 'name', 'b_date', 's_roll', 's_year'];

    public const OPTIONAL = ['fname', 'mname', 'h_roll', 'h_year', 'nid_no'];

    public function read(string $path, string $extension): array
    {
        try {
            return $this->readWorkbook($path, $extension);
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Unable to read this workbook. Upload a valid XLS/XLSX file with plain values.', 0, $e);
        }
    }

    private function readWorkbook(string $path, string $extension): array
    {
        if (! in_array($extension, ['xls', 'xlsx'], true)) {
            throw new InvalidArgumentException('Upload an XLS or XLSX workbook.');
        }
        $reader = IOFactory::createReader($extension === 'xls' ? 'Xls' : 'Xlsx');
        $reader->setReadDataOnly(false);
        $info = $reader->listWorksheetInfo($path);
        if (! $info) {
            throw new InvalidArgumentException('No worksheet found.');
        }
        $info = $info[0];
        if ($info['totalRows'] > config('matching.max_rows') + 1 || $info['totalColumns'] > config('matching.max_columns')) {
            throw new InvalidArgumentException('Limit: 10,000 rows and 200 columns on the first worksheet.');
        }
        $reader->setLoadSheetsOnly([$info['worksheetName']]);
        $book = $reader->load($path);
        try {
            $sheet = $book->getSheet(0);
            $headers = [];
            for ($c = 1; $c <= $info['totalColumns']; $c++) {
                $cell = $sheet->getCell([$c, 1]);
                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    throw new InvalidArgumentException('Headers must be plain text.');
                }
                $headers[] = (string) $cell->getValue();
            }
            while ($headers && end($headers) === '') {
                array_pop($headers);
            }
            $this->validateHeaders($headers);
            $records = [];
            $keys = [];
            $users = [];
            $regs = [];
            for ($r = 2; $r <= $info['totalRows']; $r++) {
                $data = [];
                $excelDate = null;
                foreach ($headers as $i => $header) {
                    $cell = $sheet->getCell([$i + 1, $r]);
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        throw new InvalidArgumentException("Row $r: formulas are not supported. Paste values before uploading.");
                    }
                    $data[$header] = (string) $cell->getFormattedValue();
                    if (strlen($data[$header]) > 32767) {
                        throw new InvalidArgumentException("Row $r: a cell exceeds Excel's 32,767 character limit.");
                    }
                    if ($header === 'b_date' && Date::isDateTime($cell) && is_numeric($cell->getValue())) {
                        $excelDate = Date::excelToDateTimeObject($cell->getValue())->format('Y-m-d');
                    }
                }
                if (! array_filter($data, fn ($v) => trim($v) !== '')) {
                    continue;
                }
                try {
                    $identity = $this->identity($data, $excelDate);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException("Row $r: ".$e->getMessage());
                }
                $key = json_encode(array_intersect_key($identity, array_flip(MatchEngine::EXACT)), JSON_UNESCAPED_UNICODE);
                if (isset($keys[$key])) {
                    throw new InvalidArgumentException("Rows {$keys[$key]} and $r: duplicate exact identity in the same post.");
                }
                foreach (['user' => &$users, 'reg' => &$regs] as $field => &$seen) {
                    $value = trim($data[$field]);
                    if (isset($seen[$value])) {
                        throw new InvalidArgumentException("Row $r: duplicate $field in this post.");
                    }$seen[$value] = $r;
                }unset($seen);
                $keys[$key] = $r;
                $records[] = ['source_row' => $r, 'data' => $data, 'identity' => $identity];
            }
            if (! $records) {
                throw new InvalidArgumentException('The worksheet has no candidate rows.');
            }

            return ['headers' => $headers, 'records' => $records];
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function validateHeaders(array $headers): void
    {
        if (! $headers || count(array_filter($headers, fn ($header) => trim($header) === '')) > 0) {
            throw new InvalidArgumentException('Every used column must have a nonblank header.');
        }
        if (count($headers) !== count(array_unique(array_map('mb_strtolower', $headers)))) {
            throw new InvalidArgumentException('Column headers must be unique, including letter case.');
        }
        foreach (self::REQUIRED as $field) {
            if (! in_array($field, $headers, true)) {
                throw new InvalidArgumentException("Required column missing: $field. Matching field names are case sensitive.");
            }
        }
        foreach ($headers as $h) {
            if (mb_strlen($h) > 120) {
                throw new InvalidArgumentException('Column headers must be at most 120 characters.');
            }
        }
    }

    public function identity(array $data, ?string $excelDate = null): array
    {
        foreach (self::REQUIRED as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                throw new InvalidArgumentException("$field is required.");
            }
        }
        $out = [];
        foreach (array_merge(MatchEngine::EXACT, self::OPTIONAL) as $field) {
            $out[$field] = trim((string) ($data[$field] ?? ''));
        }
        $out['b_date'] = $excelDate ?? $this->date($out['b_date']);
        foreach (['s_year', 'h_year'] as $field) {
            if ($out[$field] !== '' && ! preg_match('/^\d{4}$/D', $out[$field])) {
                throw new InvalidArgumentException("$field must contain four digits.");
            }
        }

        return $out;
    }

    public function date(string $value): string
    {
        if (preg_match('/^(\d{2})(\d{2})(\d{4}|\d{2})$/D', $value, $m)) {
            $year = (int) $m[3];
            if (strlen($m[3]) === 2) {
                $year += $year <= config('matching.birth_year_pivot', 30) ? 2000 : 1900;
            }$month = (int) $m[2];
            $day = (int) $m[1];
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $m)) {
            [$year,$month,$day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            throw new InvalidArgumentException('b_date must be DDMMYYYY, DDMMYY, YYYY-MM-DD, or an Excel date cell.');
        }
        if (! checkdate($month, $day, $year)) {
            throw new InvalidArgumentException('Invalid b_date.');
        }

return sprintf('%04d-%02d-%02d',$year,$month,$day);
    }
}
