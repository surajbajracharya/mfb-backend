<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;
class CourseSection extends Model {
    use BelongsToCompany;
    protected $fillable = ['course_id', 'title', 'description', 'order', 'company_id'];
    public function course() { return $this->belongsTo(Course::class); }
    public function lectures() { return $this->hasMany(CourseLecture::class, 'section_id')->orderBy('order'); }
    public function getTotalDurationAttribute() { return $this->lectures()->sum('duration_seconds'); }
}