<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;
class CourseLecture extends Model {
    use BelongsToCompany;
    protected $fillable = ['section_id','title','description','type','content_url','duration_seconds','is_preview','resources','order','company_id'];
    protected $casts = ['is_preview'=>'boolean', 'resources'=>'array'];
    public function section() { return $this->belongsTo(CourseSection::class, 'section_id'); }
    public function progress() { return $this->hasMany(CourseProgress::class, 'lecture_id'); }
    public function notes() { return $this->hasMany(LectureNote::class, 'lecture_id'); }
}