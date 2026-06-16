<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallengeDay extends Model
{
    protected $fillable = [
        'challenge_id', 'day_number', 'title', 'instructions',
        'video_url', 'audio_url', 'image_url', 'duration_minutes',
    ];

    protected $casts = ['day_number' => 'integer', 'duration_minutes' => 'integer'];

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function progress()
    {
        return $this->hasMany(ChallengeDayProgress::class);
    }
}
