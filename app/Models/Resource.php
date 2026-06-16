<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToCompany;
class Resource extends Model {
    use SoftDeletes, BelongsToCompany;
    protected $fillable = ['user_id','title','slug','excerpt','content','meta_title','meta_description','og_image','schema_markup','robots','thumbnail','file_url','external_url','type','is_free','price','download_count','duration_seconds','status','published_at','sort_order','company_id'];
    protected $casts = ['is_free'=>'boolean', 'published_at'=>'datetime'];
    public function author() { return $this->belongsTo(User::class, 'user_id'); }
}