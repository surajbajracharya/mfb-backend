<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;
class LectureNote extends Model {
    use BelongsToCompany;
    protected $fillable = ['user_id','lecture_id','content','timestamp_seconds','company_id'];
    public function user() { return $this->belongsTo(User::class); }
    public function lecture() { return $this->belongsTo(CourseLecture::class, 'lecture_id'); }
}