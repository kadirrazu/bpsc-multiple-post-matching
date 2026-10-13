<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\MatchingBatch;
use Closure;
use Illuminate\Http\Request;

class WorkspaceAccess
{
    public function handle(Request $r, Closure $next)
    {
        $batch = $r->route('batch');
        if ($batch && $r->user()->role === UserRole::Operator) {
            if (! $batch instanceof MatchingBatch) {
                $batch = MatchingBatch::findOrFail($batch);
            }
            abort_unless($batch->created_by !== null && (int) $batch->created_by === (int) $r->user()->id, 403);
        }

        return $next($r);
    }
}
