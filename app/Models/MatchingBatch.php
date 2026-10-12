<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchingBatch extends Model
{
    protected $guarded = ['id'];

    public function posts()
    {
        return $this->hasMany(MatchingPost::class)->orderBy('id');
    }

    public function people()
    {
        return $this->hasMany(MatchingPerson::class);
    }

    public function records()
    {
        return $this->hasManyThrough(MatchingRecord::class, MatchingPost::class);
    }
}
