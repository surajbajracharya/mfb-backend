<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;
class Appointment extends Model {
    use BelongsToCompany;
    protected $fillable = ['user_id','appointment_type_id','order_item_id','scheduled_at','duration_minutes','status','meeting_link','notes','cancellation_reason','cancelled_at','consent_data','consented_at','consent_pdf_path','company_id','timezone','reminder_sent_at'];
    protected $casts = ['scheduled_at'=>'datetime', 'cancelled_at'=>'datetime', 'consented_at'=>'datetime', 'consent_data'=>'array', 'duration_minutes'=>'integer'];
    public function user() { return $this->belongsTo(User::class); }
    public function type() { return $this->belongsTo(AppointmentType::class, 'appointment_type_id'); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
}