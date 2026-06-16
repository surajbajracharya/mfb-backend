<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToCompany;
class Category extends Model {
    use SoftDeletes, BelongsToCompany;
    protected $fillable = ['name', 'slug', 'description', 'image', 'parent_id', 'sort_order', 'company_id'];
    public function parent() { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children() { return $this->hasMany(Category::class, 'parent_id'); }
    public function courses() { return $this->hasMany(Course::class); }
}