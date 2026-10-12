<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchingRecord extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'identity' => 'array', 'suggestions' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function post()
    {
        return $this->belongsTo(MatchingPost::class, 'matching_post_id');
    }

    public function person()
    {
        return $this->belongsTo(MatchingPerson::class, 'matching_person_id');
    }
}
