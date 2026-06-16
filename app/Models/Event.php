<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToCompany;
class Event extends Model {
    use SoftDeletes, BelongsToCompany;
    protected $fillable = ['title','slug','description','is_online','hero_image','organizer_name','organizer_email','organizer_phone','venue_name','venue_address','venue_phone','meeting_link','starts_at','ends_at','timezone','price','capacity','tickets_sold','status','company_id','meta_title','meta_description','og_image','schema_markup','robots'];
    protected $casts = ['starts_at'=>'datetime', 'ends_at'=>'datetime', 'price'=>'decimal:2'];
    public function tickets() { return $this->hasMany(EventTicket::class); }
    public function categories() { return $this->belongsToMany(EventCategory::class, 'event_category_event'); }
    public function getAvailableTicketsAttribute(): int { return $this->capacity - $this->tickets_sold; }
    public function isSoldOut(): bool { return $this->tickets_sold >= $this->capacity; }
}