<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToCompany;
class Course extends Model {
    use SoftDeletes, BelongsToCompany;
    protected $fillable = ['user_id','category_id','sort_order','title','slug','short_description','description','thumbnail','intro_video','price','compare_price','type','status','level','language','duration_hours','has_certificate','what_you_learn','requirements','company_id','meta_title','meta_description','og_image','schema_markup','robots'];
    protected $casts = ['price'=>'decimal:2','compare_price'=>'decimal:2','has_certificate'=>'boolean','what_you_learn'=>'array','requirements'=>'array'];
    public function instructor() { return $this->belongsTo(User::class, 'user_id'); }
    public function category() { return $this->belongsTo(Category::class); }
    public function sections() { return $this->hasMany(CourseSection::class)->orderBy('order'); }
    public function lectures() { return $this->hasManyThrough(CourseLecture::class, CourseSection::class, 'course_id', 'section_id'); }
    public function enrollments() { return $this->hasMany(Enrollment::class); }
    public function reviews() { return $this->hasMany(CourseReview::class); }
    public function getAverageRatingAttribute() { return round($this->reviews()->avg('rating'), 1); }
}