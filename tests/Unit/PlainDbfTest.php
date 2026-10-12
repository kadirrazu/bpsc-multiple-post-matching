<?php

namespace Tests\Unit;

use App\Services\Matching\PlainDbf;
use Tests\TestCase;

class PlainDbfTest extends TestCase
{
    public function test_single_dbf_header_records_aliases_and_utf8_chunks(): void
    {
        $long = str_repeat('বাংলা', 30);
        $table = ['headers' => ['reg', 'user_123456', 'Very Long Dynamic Field', 'Bangla'], 'rows' => [['000123', 'U000001', '=literal', $long]]];
        $writer = new PlainDbf;
        $fields = $writer->schema($table);
        $path = tempnam(sys_get_temp_dir(), 'dbf-test-');
        try {
            $writer->write($table, $path);
            $bytes = file_get_contents($path);
            $this->assertSame(3, ord($bytes[0]));
            $this->assertSame(1, unpack('V', substr($bytes, 4, 4))[1]);
            $offset = unpack('v', substr($bytes, 8, 2))[1];
            $recordLength = unpack('v', substr($bytes, 10, 2))[1];
            $this->assertSame($offset + $recordLength + 1, strlen($bytes));
            $this->assertSame("\x1A", substr($bytes, -1));
            $this->assertSame('reg', $fields[0]['name']);
            $this->assertSame('USR_123456', $fields[1]['name']);
            $this->assertSame('000123', rtrim(substr($bytes, $offset + 1, $fields[0]['length'])));
            $position = $offset + 1;
            $restored = '';
            foreach ($fields as $i => $field) {
                $name = rtrim(substr($bytes, 32 + $i * 32, 11), "\0");
                $this->assertSame($field['name'], $name);
                $value = substr($bytes, $position, $field['length']);
                if ($field['source'] === 'Bangla') {
                    $restored .= rtrim($value);
                }$position += $field['length'];
            }
            $this->assertSame($long, $restored);
        } finally {
            @unlink($path);
        }
    }
}
