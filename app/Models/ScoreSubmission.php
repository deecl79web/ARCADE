<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreSubmission extends Model
{
    protected $guarded = [];

    protected $casts = ['flagged' => 'boolean'];

    public function session()
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }
}
