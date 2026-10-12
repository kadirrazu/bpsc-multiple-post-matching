<?php

namespace Tests\Unit;

use App\Services\Matching\DynamicWorkbook;
use App\Services\Matching\MatchEngine;
use Tests\TestCase;

class MatchEngineTest extends TestCase
{
    private function identity(array $extra = []): array
    {
        return array_replace(['name' => 'ABDUL KADIR', 'b_date' => '1997-10-10', 's_roll' => '001234', 's_year' => '2013', 'fname' => '', 'mname' => '', 'h_roll' => '', 'h_year' => '', 'nid_no' => ''], $extra);
    }

    private function engine(array $identity): MatchEngine
    {
        return new MatchEngine([['person_id' => 7, 'record_id' => 11, 'identity' => $identity]]);
    }

    public function test_exact_core_links_even_when_optional_fields_are_blank(): void
    {
        $this->assertSame('EXACT', $this->engine($this->identity())->match($this->identity())['status']);
    }

    public function test_partial_name_requires_review_with_full_dob_and_ssc(): void
    {
        $this->assertSame('REVIEW', $this->engine($this->identity())->match($this->identity(['name' => 'MD ABDUL KADIR']))['status']);
    }

    public function test_different_dob_does_not_match(): void
    {
        $this->assertSame('NEW', $this->engine($this->identity())->match($this->identity(['b_date' => '1997-10-11']))['status']);
    }

    public function test_different_ssc_does_not_match(): void
    {
        $this->assertSame('NEW', $this->engine($this->identity())->match($this->identity(['s_roll' => '001235']))['status']);
    }

    public function test_optional_full_field_conflict_needs_review(): void
    {
        $this->assertSame('REVIEW', $this->engine($this->identity(['nid_no' => '123']))->match($this->identity(['nid_no' => '456']))['status']);
    }

    public function test_shared_surname_does_not_match(): void
    {
        $this->assertFalse((new MatchEngine)->similar('RAHIM HOSSAIN', 'KARIM HOSSAIN'));
    }

    public function test_ambiguous_exact_identities_require_review(): void
    {
        $id = $this->identity();
        $engine = new MatchEngine([['person_id' => 1, 'record_id' => 1, 'identity' => $id], ['person_id' => 2, 'record_id' => 2, 'identity' => $id]]);
        $this->assertSame('REVIEW', $engine->match($id)['status']);
    }

    public function test_dates_use_pivot_and_validate(): void
    {
        $reader = new DynamicWorkbook;
        $this->assertSame('2000-10-10', $reader->date('101000'));
        $this->assertSame('1997-10-10', $reader->date('101097'));
        $this->assertSame('2005-10-10', $reader->date('101005'));
        $this->expectException(\InvalidArgumentException::class);
        $reader->date('31022000');
    }

    public function test_original_data_is_not_normalized_in_place(): void
    {
        $raw = ['user' => '00001', 'reg' => '00002', 'name' => ' ABDUL KADIR ', 'b_date' => '101097', 's_roll' => '001234', 's_year' => '2013', 'custom' => '  keep spaces  '];
        $id = (new DynamicWorkbook)->identity($raw);
        $this->assertSame('1997-10-10', $id['b_date']);
        $this->assertSame('101097', $raw['b_date']);
        $this->assertSame('  keep spaces  ', $raw['custom']);
    }

    public function test_identical_single_word_optional_names_are_full_matches(): void
    {
        $this->assertTrue((new MatchEngine)->similar('ALI', 'ALI'));
        $this->assertSame('EXACT', $this->engine($this->identity(['fname' => 'ALI']))->match($this->identity(['fname' => 'ALI']))['status']);
    }
}
