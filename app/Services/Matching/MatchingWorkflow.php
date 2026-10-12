<?php

namespace App\Services\Matching;

use App\Models\MatchingBatch;
use App\Models\MatchingPerson;
use App\Models\MatchingPost;
use App\Models\MatchingRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchingWorkflow
{
    public function catalog(MatchingBatch $batch): array
    {
        return $batch->records()->whereNotNull('matching_person_id')->orderBy('matching_records.id')->get(['matching_records.id', 'matching_person_id', 'identity'])->map(fn ($r) => ['record_id' => $r->id, 'person_id' => $r->matching_person_id, 'identity' => $r->identity])->all();
    }

    public function import(MatchingPost $post, array $workbook, string $filename): void
    {
        DB::transaction(function () use ($post, $workbook, $filename) {
            $batch = MatchingBatch::whereKey($post->matching_batch_id)->lockForUpdate()->firstOrFail();
            $post = MatchingPost::whereKey($post->id)->lockForUpdate()->firstOrFail();
            if ($post->records()->exists()) {
                throw ValidationException::withMessages(['file' => 'This post already has data. Delete its dataset before importing again.']);
            }
            $engine = new MatchEngine($this->catalog($batch));
            foreach ($workbook['records'] as $row) {
                $plan = $engine->match($row['identity']);
                $person = null;
                if ($plan['status'] === 'EXACT') {
                    $person = array_key_first($plan['suggestions']);
                } elseif ($plan['status'] === 'NEW') {
                    $person = $batch->people()->create()->id;
                }
                if ($person && $post->records()->where('matching_person_id', $person)->exists()) {
                    throw ValidationException::withMessages(['file' => "Row {$row['source_row']}: this person already has a record in the same post."]);
                }
                $post->records()->create($row + ['matching_person_id' => $person, 'status' => $plan['status'], 'suggestions' => $plan['suggestions']]);
            }
            $post->update(['headers' => $workbook['headers'], 'filename' => $filename, 'row_count' => count($workbook['records'])]);
            $this->refreshPending($batch);
        });
    }

    public function refreshPending(MatchingBatch $batch): void
    {
        $engine = new MatchEngine($this->catalog($batch));
        foreach ($batch->records()->where('status', 'REVIEW')->orderBy('matching_records.id')->get() as $record) {
            $plan = $engine->match($record->identity);
            // A pending review never changes identity groups without an operator's decision.
            $record->update(['suggestions' => $plan['suggestions']]);
        }
    }

    public function decide(MatchingRecord $record, string $decision, int $userId): void
    {
        DB::transaction(function () use ($record, $decision, $userId) {
            $batch = MatchingBatch::whereKey($record->post->matching_batch_id)->lockForUpdate()->firstOrFail();
            $record = MatchingRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($record->status !== 'REVIEW') {
                throw ValidationException::withMessages(['decision' => 'This row has already been reviewed.']);
            }
            if ($decision === 'new') {
                $person = $batch->people()->create()->id;
                $status = 'NEW';
            } else {
                $person = (int) substr($decision, 5);
                $plan = (new MatchEngine($this->catalog($batch)))->match($record->identity);
                if (! isset($plan['suggestions'][$person])) {
                    throw ValidationException::withMessages(['decision' => 'This match is no longer available. Refresh the review page.']);
                }
                if (! MatchingPerson::where('matching_batch_id', $batch->id)->whereKey($person)->exists()) {
                    abort(404);
                }
                if ($record->post->records()->where('matching_person_id', $person)->exists()) {
                    throw ValidationException::withMessages(['decision' => 'The selected person already has a record in this post.']);
                }
                $status = 'CONFIRMED';
            }
            $record->update(['matching_person_id' => $person, 'status' => $status, 'reviewed_by' => $userId, 'reviewed_at' => now()]);
            $this->refreshPending($batch);
        });
    }

    public function reset(MatchingPost $post): void
    {
        DB::transaction(function () use ($post) {
            $batch = MatchingBatch::whereKey($post->matching_batch_id)->lockForUpdate()->firstOrFail();
            $post->records()->delete();
            $post->update(['headers' => null, 'filename' => null, 'row_count' => 0]);
            $batch->people()->whereDoesntHave('records')->delete();
            $this->refreshPending($batch);
        });
    }
}
