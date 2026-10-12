<?php

namespace App\Http\Controllers;

use App\Models\MatchingBatch;
use App\Models\MatchingPost;
use App\Models\MatchingRecord;
use App\Services\Matching\DynamicWorkbook;
use App\Services\Matching\MatchingExport;
use App\Services\Matching\MatchingWorkflow;
use App\Services\Matching\PlainDbf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MatchingController extends Controller
{
    public function index()
    {
        return view('matching.index', ['batches' => MatchingBatch::withCount(['posts', 'records'])->latest()->paginate(25)]);
    }

    public function store(Request $r)
    {
        $data = $r->validate(['title' => 'required|string|max:255']);
        $batch = MatchingBatch::create($data);

        return redirect()->route('matching.show', $batch)->with('success', 'Matching workspace created.');
    }

    public function show(MatchingBatch $batch)
    {
        return view('matching.show', ['batch' => $batch, 'posts' => $batch->posts()->withCount(['records', 'records as pending_count' => fn ($q) => $q->where('status', 'REVIEW')])->get(), 'pending' => $batch->records()->where('status', 'REVIEW')->count(), 'people' => $batch->people()->count()]);
    }

    public function post(Request $r, MatchingBatch $batch)
    {
        $data = $r->validate(['code' => ['required', 'string', 'regex:/^[A-Za-z0-9]{1,20}$/D'], 'title' => 'required|string|max:255']);
        DB::transaction(function () use ($batch, $data) {
            MatchingBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->posts()->where('code', $data['code'])->exists()) {
                throw ValidationException::withMessages(['code' => 'This post code already exists in the workspace.']);
            }$batch->posts()->create($data);
        });

        return back()->with('success', 'Post created.');
    }

    private function scoped(MatchingBatch $batch, MatchingPost $post): void
    {
        abort_unless($post->matching_batch_id === $batch->id, 404);
    }

    public function upload(Request $r, MatchingBatch $batch, MatchingPost $post, DynamicWorkbook $reader, MatchingWorkflow $workflow)
    {
        $this->scoped($batch, $post);
        $r->validate(['file' => 'required|file|mimes:xls,xlsx|max:'.config('matching.max_file_kb')]);
        $file = $r->file('file');
        try {
            $data = $reader->read($file->getRealPath(), strtolower($file->getClientOriginalExtension()));
            $workflow->import($post, $data, mb_substr(basename($file->getClientOriginalName()), 0, 255));
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return back()->with('success', 'All columns saved. Exact matches linked; possible matches are waiting for review.');
    }

    public function records(Request $r, MatchingBatch $batch, MatchingPost $post)
    {
        $this->scoped($batch, $post);
        $data = $r->validate(['q' => 'nullable|string|max:255', 'status' => ['nullable', Rule::in(['NEW', 'EXACT', 'REVIEW', 'CONFIRMED'])]]);
        $query = $post->records()->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s));
        if (! empty($data['q'])) {
            $needle = mb_strtolower($data['q']);
            $ids = $query->get(['id', 'data'])->filter(fn ($row) => collect($row->data)->contains(fn ($v) => str_contains(mb_strtolower((string) $v), $needle)))->pluck('id');
            $query->whereIn('id', $ids);
        }

        return view('matching.records', ['batch' => $batch, 'post' => $post, 'records' => $query->paginate(25)->withQueryString()]);
    }

    public function review(MatchingBatch $batch)
    {
        $records = $batch->records()->with('post')->where('status', 'REVIEW')->orderBy('matching_records.id')->paginate(10);
        $ids = [];
        foreach ($records as $r) {
            foreach ($r->suggestions ?? [] as $s) {
                $ids[] = $s['record_id'];
            }
        }$suggested = MatchingRecord::with('post')->whereIn('id', $ids)->get()->keyBy('id');

        return view('matching.review', compact('batch', 'records', 'suggested'));
    }

    public function decide(Request $r, MatchingBatch $batch, MatchingRecord $record, MatchingWorkflow $workflow)
    {
        abort_unless($record->post->matching_batch_id === $batch->id, 404);
        $data = $r->validate(['decision' => ['required', 'regex:/^(new|link:[1-9]\d*)$/D'], 'confirm' => 'accepted']);
        try {
            $workflow->decide($record, $data['decision'], $r->user()->id);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['decision' => $e->getMessage()]);
        }

        return back()->with('success', 'Review decision saved.');
    }

    public function reset(Request $r, MatchingBatch $batch, MatchingPost $post, MatchingWorkflow $workflow)
    {
        $this->scoped($batch, $post);
        $r->validate(['confirmation' => ['required', Rule::in(['DELETE'])]]);
        $workflow->reset($post);

        return redirect()->route('matching.show', $batch)->with('success', 'Post dataset deleted. You can upload a replacement.');
    }

    public function export(Request $r, MatchingBatch $batch, MatchingExport $export, ?MatchingPost $post = null)
    {
        if ($post) {
            $this->scoped($batch, $post);
        }$r->validate(['matched' => ['nullable', 'boolean'], 'mode' => ['nullable', Rule::in(['all', 'unique'])], 'format' => ['nullable', Rule::in(['xlsx', 'dbf'])]]);
        $path = tempnam(storage_path('app'), 'matching-');
        try {
            $table = $export->table($batch, $post, $r->boolean('matched'), $r->input('mode', 'unique') !== 'all');
            if ($r->input('format', 'xlsx') === 'dbf') {
                (new PlainDbf)->write($table, $path);
            } else {
                $export->write($table, $path);
            }
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }

        return response()->download($path, 'matching-'.($post ? $post->code : 'combined-'.$r->input('mode', 'unique').'-'.$batch->id).'-'.now()->format('Ymd-His').'.'.$r->input('format', 'xlsx'), ['Content-Type' => $r->input('format', 'xlsx') === 'dbf' ? 'application/octet-stream' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }

    public function sample(MatchingExport $export)
    {
        $table = ['headers' => array_merge(DynamicWorkbook::REQUIRED, DynamicWorkbook::OPTIONAL, ['district', 'custom_field']), 'rows' => [['U000001', '0012345678', 'SAMPLE CANDIDATE', '10101997', '001234', '2013', 'SAMPLE FATHER', '', '005678', '2015', '', 'Dhaka', 'Any additional value']]];
        $path = tempnam(storage_path('app'), 'sample-');
        $export->write($table, $path);

        return response()->download($path, 'matching-import-sample.xlsx')->deleteFileAfterSend(true);
    }
}
