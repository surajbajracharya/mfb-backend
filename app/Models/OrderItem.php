<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;
class OrderItem extends Model {
    use BelongsToCompany;
    protected $fillable = ['order_id','purchasable_id','purchasable_type','name','price','quantity','subtotal','meta','company_id','is_refunded','refunded_at'];
    protected $casts = ['price'=>'decimal:2', 'subtotal'=>'decimal:2', 'meta'=>'array', 'is_refunded'=>'boolean', 'refunded_at'=>'datetime'];
    public function order() { return $this->belongsTo(Order::class); }
    public function purchasable() { return $this->morphTo(); }
    public function appointment() { return $this->hasOne(\App\Models\Appointment::class, 'order_item_id'); }
    public function eventTicket() { return $this->hasOne(\App\Models\EventTicket::class, 'order_item_id'); }
}