<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchingPerson extends Model
{
    protected $guarded = ['id'];

    public function records()
    {
        return $this->hasMany(MatchingRecord::class)->orderBy('id');
    }
}
