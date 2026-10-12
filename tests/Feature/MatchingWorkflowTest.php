<?php

namespace Tests\Feature;

use App\Models\MatchingBatch;
use App\Models\User;
use App\Services\Matching\DynamicWorkbook;
use App\Services\Matching\MatchingExport;
use App\Services\Matching\MatchingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MatchingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function book(array $extra = []): array
    {
        $raw = array_replace(['user' => '00001', 'reg' => '0012345678', 'name' => 'ABDUL KADIR', 'b_date' => '101097', 's_roll' => '001234', 's_year' => '2013', 'district' => 'Dhaka', 'custom' => '=keep this literal'], $extra);

        return ['headers' => array_keys($raw), 'records' => [['source_row' => 2, 'data' => $raw, 'identity' => (new DynamicWorkbook)->identity($raw)]]];
    }

    private function setupPosts(): array
    {
        $batch = MatchingBatch::create(['title' => 'Test recruitment']);
        $a = $batch->posts()->create(['code' => '000001', 'title' => 'First']);
        $b = $batch->posts()->create(['code' => '000002', 'title' => 'Second']);

        return [$batch, $a, $b];
    }

    private function user(string $role = 'admin'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    public function test_exact_match_preserves_dynamic_fields_and_combined_post_values(): void
    {
        [$batch,$a,$b] = $this->setupPosts();
        $flow = new MatchingWorkflow;
        $flow->import($a, $this->book(), 'one.xlsx');
        $flow->import($b, $this->book(['user' => '00009', 'reg' => '0000000009', 'district' => 'Rajshahi', 'extra' => 'বাংলা']), 'two.xlsx');
        $this->assertSame(1, $batch->people()->count());
        $this->assertSame('বাংলা', $b->records()->first()->data['extra']);
        $table = (new MatchingExport)->table($batch);
        $this->assertCount(1, $table['rows']);
        $row = array_combine($table['headers'], $table['rows'][0]);
        $this->assertSame('0012345678', $row['reg_000001']);
        $this->assertSame('0000000009', $row['reg_000002']);
        $this->assertSame('Dhaka', $row['district']);
        $this->assertSame('000001|000002', $row['matching_post']);
        $this->assertSame('2', $row['matched_post_count']);
        foreach (['district_000001', 'district_000002', 'extra_000002', 'extra', 'matching_group_id'] as $removed) {
            $this->assertNotContains($removed, $table['headers']);
        }
        $all = (new MatchingExport)->table($batch, null, false, false);
        $this->assertSame(['Dhaka', 'Rajshahi'], array_column($all['rows'], array_search('district', $all['headers'], true)));
        $individual = (new MatchingExport)->table($batch, $a);
        $this->assertContains('custom', $individual['headers']);
        $this->assertContains('reg_000002', $individual['headers']);
        $individualRow = array_combine($individual['headers'], $individual['rows'][0]);
        $this->assertSame('000001|000002', $individualRow['matching_post']);
        $this->assertSame('2', $individualRow['matched_post_count']);
    }

    public function test_partial_match_blocks_export_until_confirmed(): void
    {
        [$batch,$a,$b] = $this->setupPosts();
        $flow = new MatchingWorkflow;
        $flow->import($a, $this->book(), 'one.xlsx');
        $flow->import($b, $this->book(['name' => 'MD ABDUL KADIR']), 'two.xlsx');
        $record = $b->records()->first();
        $this->assertSame('REVIEW', $record->status);
        try {
            (new MatchingExport)->table($batch);
            $this->fail('Pending review exported.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('export', $e->errors());
        }$flow->decide($record, 'link:'.$a->records()->first()->matching_person_id, $this->user()->id);
        $this->assertSame(1, $batch->people()->count());
        $this->assertSame('CONFIRMED', $record->fresh()->status);
        $this->assertCount(1, (new MatchingExport)->table($batch)['rows']);
    }

    public function test_review_can_keep_people_separate(): void
    {
        [$batch,$a,$b] = $this->setupPosts();
        $flow = new MatchingWorkflow;
        $flow->import($a, $this->book(), 'one.xlsx');
        $flow->import($b, $this->book(['name' => 'MD ABDUL KADIR']), 'two.xlsx');
        $flow->decide($b->records()->first(), 'new', $this->user()->id);
        $this->assertSame(2, $batch->people()->count());
        $this->assertCount(0, (new MatchingExport)->table($batch, null, true)['rows']);
    }

    public function test_import_is_atomic_and_does_not_overwrite_existing_data(): void
    {
        [$batch,$a] = $this->setupPosts();
        $flow = new MatchingWorkflow;
        $flow->import($a, $this->book(), 'one.xlsx');
        try {
            $flow->import($a, $this->book(['name' => 'OTHER PERSON']), 'replacement.xlsx');
            $this->fail('Overwrite accepted.');
        } catch (ValidationException $e) {
            $this->assertSame('one.xlsx', $a->fresh()->filename);
            $this->assertSame(1, $a->records()->count());
        }
    }

    public function test_reset_keeps_other_posts_and_allows_reimport(): void
    {
        [$batch,$a,$b] = $this->setupPosts();
        $flow = new MatchingWorkflow;
        $flow->import($a, $this->book(), 'one.xlsx');
        $flow->import($b, $this->book(), 'two.xlsx');
        $flow->reset($a);
        $this->assertSame(1, $batch->people()->count());
        $this->assertSame(1, $b->records()->count());
        $flow->import($a, $this->book(), 'new.xlsx');
        $this->assertSame(1, $batch->people()->count());
    }

    public function test_source_collision_does_not_overwrite_generated_reg_column(): void
    {
        [$batch,$a] = $this->setupPosts();
        (new MatchingWorkflow)->import($a, $this->book(['reg_000001' => 'ORIGINAL VALUE']), 'one.xlsx');
        $table = (new MatchingExport)->table($batch, $a);
        $row = array_combine($table['headers'], $table['rows'][0]);
        $this->assertSame('ORIGINAL VALUE', $row['reg_000001']);
        $this->assertSame('0012345678', $row['reg_000001__2']);
    }

    public function test_spreadsheet_export_is_text_and_retains_leading_zeros(): void
    {
        [$batch,$a] = $this->setupPosts();
        (new MatchingWorkflow)->import($a, $this->book(), 'one.xlsx');
        $export = new MatchingExport;
        $table = $export->table($batch, $a);
        $path = tempnam(sys_get_temp_dir(), 'export-');
        try {
            $export->write($table, $path);
            $book = IOFactory::load($path);
            $this->assertSame(1, $book->getSheetCount());
            $sheet = $book->getActiveSheet();
            $col = array_search('custom', $table['headers'], true) + 1;
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell([$col, 2])->getDataType());
            $this->assertSame('=keep this literal', $sheet->getCell([$col, 2])->getValue());
            $col = array_search('reg', $table['headers'], true) + 1;
            $this->assertSame('0012345678', $sheet->getCell([$col, 2])->getValue());
            $book->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
    }

    public function test_upload_reads_all_columns_and_real_excel_dates(): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $headers = ['user', 'reg', 'name', 'b_date', 's_roll', 's_year', ' Arbitrary Field '];
        $values = ['00001', '00002', 'ABDUL KADIR', '101097', '001234', '2013', '  বাংলা  '];
        foreach ($headers as $i => $h) {
            $sheet->setCellValueExplicit([$i + 1, 1], $h, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([$i + 1, 2], $values[$i], DataType::TYPE_STRING);
        }$sheet->setCellValue([4, 2], Date::PHPToExcel(new \DateTime('1997-10-10')));
        $sheet->getStyle('D2')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $path = tempnam(sys_get_temp_dir(), 'upload-');
        try {
            (new Xlsx($book))->save($path);
            $read = (new DynamicWorkbook)->read($path, 'xlsx');
            $this->assertSame('  বাংলা  ', $read['records'][0]['data'][' Arbitrary Field ']);
            $this->assertSame('10/10/1997', $read['records'][0]['data']['b_date']);
            $this->assertSame('1997-10-10', $read['records'][0]['identity']['b_date']);
        } finally {
            @unlink($path);
            $book->disconnectWorksheets();
        }
    }

    public function test_login_required_and_operator_cannot_manage_users(): void
    {
        $this->get('/matching')->assertRedirect('/login');
        $this->actingAs($this->user('operator'))->get('/users')->assertForbidden();
        $this->get('/matching')->assertOk();
    }

    public function test_batch_boundaries_are_enforced(): void
    {
        [$batch,$a] = $this->setupPosts();
        $other = MatchingBatch::create(['title' => 'Other']);
        $this->actingAs($this->user())->get(route('matching.records', [$other, $a]))->assertNotFound();
    }

    public function test_old_public_choice_routes_are_removed(): void
    {
        $this->get('/candidate/events/1/sign-in')->assertNotFound();
        $this->get('/choice-events')->assertNotFound();
    }

    public function test_workflow_views_and_exports_are_renderable(): void
    {
        [$batch,$a,$b] = $this->setupPosts();
        $flow = new MatchingWorkflow;
        $flow->import($a, $this->book(), 'one.xlsx');
        $flow->import($b, $this->book(['name' => 'MD ABDUL KADIR']), 'two.xlsx');
        $this->actingAs($this->user());
        $this->get(route('matching.show', $batch))->assertOk()->assertSee('000001');
        $this->get(route('matching.records', [$batch, $a]))->assertOk()->assertSee('custom');
        $this->get(route('matching.review', $batch))->assertOk()->assertSee('Remarks')->assertSee('Confirm Match')->assertSee('Reject Match')->assertSee('Save Decision');
        $flow->decide($b->records()->first(), 'new', auth()->id());
        $this->get(route('matching.post-export', [$batch, $a]))->assertOk()->assertDownload();
        $this->get('/users')->assertOk();
    }

    public function test_login_and_inactive_account_rejection(): void
    {
        $user = $this->user();
        $this->get('/login')->assertOk();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $user->update(['is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_xls_upload_http_endpoint_preserves_unknown_columns(): void
    {
        [$batch,$post] = $this->setupPosts();
        $data = $this->book()['records'][0]['data'];
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        foreach (array_keys($data) as $i => $h) {
            $sheet->setCellValueExplicit([$i + 1, 1], $h, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([$i + 1, 2], $data[$h], DataType::TYPE_STRING);
        }
        $path = tempnam(sys_get_temp_dir(), 'xls-');
        try {
            (new Xls($book))->save($path);
            $file = new UploadedFile($path, 'post.xls', 'application/vnd.ms-excel', null, true);
            $this->actingAs($this->user())->post(route('matching.upload', [$batch, $post]), ['file' => $file])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('=keep this literal', $post->records()->first()->data['custom']);
            $this->assertSame('0012345678', $post->records()->first()->data['reg']);
        } finally {
            @unlink($path);
            $book->disconnectWorksheets();
        }
    }

    public function test_formulas_and_missing_required_columns_are_rejected(): void
    {
        $reader = new DynamicWorkbook;
        try {
            $reader->validateHeaders(['user', 'reg', 'name']);
            $this->fail('Missing fields accepted.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('b_date', $e->getMessage());
        }
        $data = $this->book()['records'][0]['data'];
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        foreach (array_keys($data) as $i => $h) {
            $sheet->setCellValueExplicit([$i + 1, 1], $h, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([$i + 1, 2], $data[$h], DataType::TYPE_STRING);
        }
        $sheet->setCellValue('H2', '=1+1');
        $path = tempnam(sys_get_temp_dir(), 'formula-');
        try {
            (new Xlsx($book))->save($path);
            $this->expectException(\InvalidArgumentException::class);
            $reader->read($path, 'xlsx');
        } finally {
            @unlink($path);
            $book->disconnectWorksheets();
        }
    }

    public function test_combined_all_and_unique_follow_added_post_then_registration_order(): void
    {
        [$batch,$a,$b] = $this->setupPosts();
        $a->update(['code' => '999999']);
        $b->update(['code' => '000001']);
        $one = $this->book(['user' => 'U3', 'reg' => '0003']);
        $two = $this->book(['user' => 'U1', 'reg' => '0001', 'name' => 'OTHER PERSON', 's_roll' => '111111']);
        $two['records'][0]['source_row'] = 3;
        $one['records'][] = $two['records'][0];
        $flow = new MatchingWorkflow;
        $flow->import($a, $one, 'first.xlsx');
        $next = $this->book(['user' => 'U0', 'reg' => '0000']);
        $fresh = $this->book(['user' => 'U2', 'reg' => '0002', 'name' => 'THIRD PERSON', 's_roll' => '222222']);
        $fresh['records'][0]['source_row'] = 3;
        $next['records'][] = $fresh['records'][0];
        $flow->import($b, $next, 'second.xlsx');
        $export = new MatchingExport;
        $all = $export->table($batch, null, false, false);
        $unique = $export->table($batch);
        $col = array_search('reg', $all['headers'], true);
        $this->assertSame(['0001', '0003', '0000', '0002'], array_column($all['rows'], $col));
        $this->assertSame(['0001', '0003', '0002'], array_column($unique['rows'], array_search('reg', $unique['headers'], true)));
        $this->assertSame(['0001', '0003'], array_column($export->table($batch, $a)['rows'], array_search('reg', $export->table($batch, $a)['headers'], true)));
    }

    public function test_dbf_downloads_are_plain_files_for_each_export_mode(): void
    {
        [$batch,$a] = $this->setupPosts();
        (new MatchingWorkflow)->import($a, $this->book(), 'one.xlsx');
        $this->actingAs($this->user());
        foreach ([route('matching.post-export', [$batch, $a, 'format' => 'dbf']), route('matching.export', [$batch, 'mode' => 'all', 'format' => 'dbf']), route('matching.export', [$batch, 'mode' => 'unique', 'format' => 'dbf'])] as $url) {
            $response = $this->get($url)->assertOk()->assertDownload();
            $this->assertStringContainsString('.dbf', $response->headers->get('Content-Disposition'));
            $this->assertSame(3, ord(file_get_contents($response->baseResponse->getFile()->getPathname())[0]));
        }
    }
}
