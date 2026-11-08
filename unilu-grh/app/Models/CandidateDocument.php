<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateDocument extends Model
{
    protected $guarded = ['id'];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }
}
