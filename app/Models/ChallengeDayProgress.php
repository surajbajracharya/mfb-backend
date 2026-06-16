<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengeDayProgress extends Model
{
    protected $fillable = ['user_id', 'challenge_day_id', 'completed_at', 'mood'];

    protected $casts = ['completed_at' => 'datetime'];

    public function day()
    {
        return $this->belongsTo(ChallengeDay::class, 'challenge_day_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
