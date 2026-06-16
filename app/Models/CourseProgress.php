<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;
class CourseProgress extends Model {
    use BelongsToCompany;
    protected $fillable = ['user_id','lecture_id','completed','watched_seconds','completed_at','company_id'];
    protected $casts = ['completed'=>'boolean', 'completed_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function lecture() { return $this->belongsTo(CourseLecture::class, 'lecture_id'); }
}