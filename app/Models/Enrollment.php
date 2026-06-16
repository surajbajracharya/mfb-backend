<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;
class Enrollment extends Model {
    use BelongsToCompany;
    protected $fillable = ['user_id','course_id','order_item_id','type','expires_at','company_id'];
    protected $casts = ['expires_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function isActive(): bool { return is_null($this->expires_at) || $this->expires_at->isFuture(); }
}