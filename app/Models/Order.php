<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToCompany;
class Order extends Model {
    use SoftDeletes, BelongsToCompany;
    protected $fillable = ['order_number','user_id','subtotal','tax','total','status','payment_method','stripe_session_id','stripe_payment_intent','paypal_order_id','paypal_capture_id','paypal_payer_email','paypal_payer_id','billing_address','paid_at','company_id'];
    protected $casts = ['billing_address'=>'array', 'paid_at'=>'datetime', 'subtotal'=>'decimal:2', 'tax'=>'decimal:2', 'total'=>'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
}