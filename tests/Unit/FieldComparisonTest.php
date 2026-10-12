<?php

namespace Tests\Unit;

use App\Services\Matching\FieldComparison;
use App\Services\Matching\MatchEngine;
use PHPUnit\Framework\TestCase;

class FieldComparisonTest extends TestCase
{
    public function test_field_remarks_follow_matching_rules(): void
    {
        $service = new FieldComparison(new MatchEngine);
        foreach ([
            ['s_roll', '001234', '001234', 'Exact match'],
            ['s_roll', '001234', '1234', 'Not matched'],
            ['name', 'ABDUL KADIR', 'MD ABDUL KADIR', 'Partial match'],
            ['name', 'RAHIM HOSSAIN', 'KARIM HOSSAIN', 'Not matched'],
            ['fname', '', 'FATHER', 'Not compared'],
            ['nid_no', '', '', 'Not compared'],
            ['reg', '00001', '00001', 'Not compared'],
            ['user', 'USER1', 'USER2', 'Not compared'],
        ] as [$field,$left,$right,$label]) {
            $this->assertSame($label, $service->compare($field, $left, $right)['label']);
        }
    }
}
