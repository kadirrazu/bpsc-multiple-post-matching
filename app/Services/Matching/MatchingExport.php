<?php

namespace App\Services\Matching;

use App\Models\MatchingBatch;
use App\Models\MatchingPost;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MatchingExport
{
    public function table(MatchingBatch $batch, ?MatchingPost $post = null, bool $matchedOnly = false, bool $unique = true): array
    {
        return DB::transaction(function () use ($batch, $post, $matchedOnly, $unique) {
            $batch = MatchingBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($post) {
                $post = MatchingPost::whereKey($post->id)->firstOrFail();
            }

            return $this->buildTable($batch, $post, $matchedOnly, $unique);
        });
    }

    private function buildTable(MatchingBatch $batch, ?MatchingPost $post, bool $matchedOnly, bool $unique): array
    {
        if ($batch->records()->where('status', 'REVIEW')->exists()) {
            throw ValidationException::withMessages(['export' => 'Complete all pending reviews before exporting.']);
        }
        $posts = $batch->posts()->get();
        $records = $batch->records()->get()->sort(function ($a, $b) {
            $postOrder = $a->matching_post_id <=> $b->matching_post_id;
            if ($postOrder !== 0) {
                return $postOrder;
            }
            $regOrder = strcmp((string) ($a->data['reg'] ?? ''), (string) ($b->data['reg'] ?? ''));

            return $regOrder ?: ($a->id <=> $b->id);
        })->values();
        $groups = $records->groupBy('matching_person_id');
        $postCodes = $posts->pluck('code', 'id');
        $headers = [];
        $used = [];
        $exportPosts = $post ? $posts : $posts->filter(fn ($p) => is_array($p->headers) && count($p->headers) > 0);
        $allHeaders = $post ? ($post->headers ?? []) : ($exportPosts->first()?->headers ?? []);
        if (! $post) {
            foreach ($exportPosts as $uploadedPost) {
                $allHeaders = array_values(array_intersect($allHeaders, $uploadedPost->headers));
            }
        }
        // Original uploaded titles take precedence over generated matching columns.
        foreach ($allHeaders as $original) {
            $used[mb_strtolower($original)] = true;
        }
        $source = [];

        $ids = [];
        $add = function (string $name) use (&$headers, &$used): string {
            $out = $name;
            $i = 2;
            while (isset($used[mb_strtolower($out)])) {
                $out = $name.'__'.$i++;
            }$used[mb_strtolower($out)] = true;
            $headers[] = $out;

            return $out;
        };
        // Reserve exact identity suffix names before assigning source-column collisions.
        foreach ($exportPosts as $p) {
            foreach (['reg', 'user'] as $field) {
                $ids[$p->id][$field] = $add($field.'_'.$p->code);
            }
        }
        $groupCol = $post ? $add('matching_group_id') : null;
        $countCol = $add('matched_post_count');
        $postCol = $add('matching_post');
        $statusCol = $add('matching_status');

        foreach ($allHeaders as $h) {
            $source[$h] = $h;
        }
        // Show original fields first; exact user/reg suffix columns follow them.
        $headers = array_values($source);
        foreach ($ids as $columns) {
            $headers = array_merge($headers, array_values($columns));
        }$headers = array_merge($headers, $post ? [$groupCol, $countCol, $postCol, $statusCol] : [$postCol, $countCol, $statusCol]);
        $rows = [];
        $selected = $post ? $records->where('matching_post_id', $post->id) : ($unique ? $records->unique('matching_person_id')->values() : $records);
        foreach ($selected as $record) {
            $group = $groups[$record->matching_person_id];
            if ($matchedOnly && $group->count() < 2) {
                continue;
            }
            $row = array_fill_keys($headers, '');
            $raw = $record->data;
            foreach ($source as $h => $out) {
                $row[$out] = $raw[$h] ?? '';
            }
            foreach ($group as $other) {
                foreach (['reg', 'user'] as $f) {
                    $row[$ids[$other->matching_post_id][$f]] = $other->data[$f] ?? '';
                }
            }
            if ($post) {
                $row[$groupCol] = (string) $record->matching_person_id;
            }
            $matchedCodes = $group->map(fn ($r) => $postCodes[$r->matching_post_id])->unique();
            $row[$postCol] = $matchedCodes->implode('|');
            $row[$countCol] = (string) $matchedCodes->count();
            $row[$statusCol] = $post ? $record->status : ($group->contains('status', 'CONFIRMED') ? 'CONFIRMED' : ($group->count() > 1 ? 'EXACT' : 'NEW'));
            $rows[] = array_values($row);
        }
        if (count($headers) > 16384) {
            throw ValidationException::withMessages(['export' => 'Combined export exceeds the Excel column limit. Export individual posts.']);
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    public function write(array $table, string $path): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Candidates');
        foreach (array_merge([$table['headers']], $table['rows']) as $r => $values) {
            foreach ($values as $c => $v) {
                $sheet->setCellValueExplicit([$c + 1, $r + 1], (string) $v, DataType::TYPE_STRING);
            }
        }
        $last = Coordinate::stringFromColumnIndex(count($table['headers']));
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$last.(count($table['rows']) + 1));
        $sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true);
        $sheet->getStyle('A1:'.$last.'1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFF0EBF8');
        for ($c = 1; $c <= count($table['headers']); $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(22);
        }
        try {
            (new Xlsx($book))->save($path);
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
