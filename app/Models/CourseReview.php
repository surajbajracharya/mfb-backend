<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToCompany;
class CourseReview extends Model {
    use SoftDeletes, BelongsToCompany;
    protected $fillable = ['user_id','course_id','rating','comment','is_approved','admin_reply','admin_reply_at','company_id'];
    protected $casts = ['is_approved'=>'boolean','admin_reply_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function course() { return $this->belongsTo(Course::class); }
}