<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToCompany;

class Challenge extends Model
{
    use SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id', 'user_id', 'title', 'slug', 'short_description',
        'description', 'thumbnail', 'badge_image', 'total_days', 'status', 'sort_order',
        'meta_title', 'meta_description', 'og_image', 'schema_markup', 'robots',
    ];

    protected $casts = ['total_days' => 'integer', 'sort_order' => 'integer'];

    public function days()
    {
        return $this->hasMany(ChallengeDay::class)->orderBy('day_number');
    }

    public function enrollments()
    {
        return $this->hasMany(ChallengeEnrollment::class);
    }
}
