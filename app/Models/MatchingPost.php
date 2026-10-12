<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchingPost extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['headers' => 'array'];
    }

    public function batch()
    {
        return $this->belongsTo(MatchingBatch::class, 'matching_batch_id');
    }

    public function records()
    {
        return $this->hasMany(MatchingRecord::class)->orderBy('source_row');
    }
}
