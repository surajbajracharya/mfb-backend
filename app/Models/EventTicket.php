<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;
class EventTicket extends Model {
    use BelongsToCompany;
    protected $fillable = ['user_id','event_id','order_item_id','ticket_code','quantity','status','qr_code','company_id'];
    public function user() { return $this->belongsTo(User::class); }
    public function event() { return $this->belongsTo(Event::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
}