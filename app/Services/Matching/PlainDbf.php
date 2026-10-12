<?php

namespace App\Services\Matching;

use Illuminate\Validation\ValidationException;

class PlainDbf
{
    public function chunks(string $value): array
    {
        if ($value === '') {
            return [''];
        }$parts = [];
        while ($value !== '') {
            $part = mb_strcut($value, 0, 254, 'UTF-8');
            $parts[] = $part;
            $value = substr($value, strlen($part));
        }

        return $parts;
    }

    public function schema(array $table): array
    {
        $fields = [];
        $used = [];
        foreach ($table['headers'] as $column => $header) {
            $widths = [1];
            foreach ($table['rows'] as $row) {
                foreach ($this->chunks((string) ($row[$column] ?? '')) as $part => $value) {
                    $widths[$part] = max($widths[$part] ?? 1, strlen($value));
                }
            }
            $base = $header;
            if (strlen($base) > 10 && str_starts_with(strtoupper($base), 'USER_')) {
                $base = 'USR_'.substr($base, 5);
            }
            foreach ($widths as $part => $width) {
                $safe = preg_replace('/[^A-Za-z0-9_]/', '_', $base);
                if (! preg_match('/[A-Za-z]/', $safe) || ! preg_match('/^[A-Za-z_]/', $safe)) {
                    $safe = sprintf('F%06d', $column + 1);
                }
                $name = count($widths) === 1 ? substr($safe, 0, 10) : substr($safe, 0, 6).'_'.sprintf('%03d', $part + 1);
                if (isset($used[strtoupper($name)])) {
                    $name = sprintf('F%06d%03d', $column + 1, $part + 1);
                }
                while (isset($used[strtoupper($name)])) {
                    $name = 'X'.substr(hash('sha256', $name), 0, 9);
                }
                $used[strtoupper($name)] = true;
                $fields[] = ['name' => $name, 'length' => $width, 'column' => $column, 'source' => $header, 'part' => $part];
            }
        }

        return $fields;
    }

    public function write(array $table, string $path): void
    {
        $fields = $this->schema($table);
        $headerLength = 33 + 32 * count($fields);
        $recordLength = 1 + array_sum(array_column($fields, 'length'));
        if (count($fields) > 255 || $recordLength > 65535) {
            throw ValidationException::withMessages(['export' => 'This dataset exceeds the plain DBF limit (255 fields / 65,535 bytes per record). Use XLSX for this dataset; no columns or values have been discarded.']);
        }
        $handle = fopen($path, 'wb');
        if (! $handle) {
            throw new \RuntimeException('Cannot create DBF output.');
        }
        try {
            $now = now();
            $header = chr(3).chr((int) $now->format('Y') - 1900).chr((int) $now->format('n')).chr((int) $now->format('j')).pack('V', count($table['rows'])).pack('v', $headerLength).pack('v', $recordLength).str_repeat("\0", 20);
            fwrite($handle, $header);
            foreach ($fields as $f) {
                fwrite($handle, str_pad($f['name'], 11, "\0").'C'.str_repeat("\0", 4).chr($f['length']).chr(0).str_repeat("\0", 14));
            }
            fwrite($handle, "\x0D");
            foreach ($table['rows'] as $row) {
                $parts = [];
                foreach ($row as $i => $value) {
                    $parts[$i] = $this->chunks((string) $value);
                }fwrite($handle, ' ');
                foreach ($fields as $f) {
                    fwrite($handle, str_pad($parts[$f['column']][$f['part']] ?? '', $f['length'], ' '));
                }
            }
            fwrite($handle, "\x1A");
        } finally {
            fclose($handle);
        }
    }
}
