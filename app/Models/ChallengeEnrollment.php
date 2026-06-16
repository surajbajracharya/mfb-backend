<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class ChallengeEnrollment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'user_id', 'challenge_id',
        'enrolled_at', 'completed_at', 'badge_awarded',
    ];

    protected $casts = [
        'enrolled_at'   => 'datetime',
        'completed_at'  => 'datetime',
        'badge_awarded' => 'boolean',
    ];

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
