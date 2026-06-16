<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToCompany;
class AppointmentType extends Model {
    use SoftDeletes, BelongsToCompany;
    protected $fillable = ['title','slug','meta_title','meta_description','og_image','schema_markup','robots','short_description','description','price','duration_minutes','break_minutes','images','is_active','sort_order','consent_template_id','mandatory_fields','company_id'];
    protected $casts = ['price'=>'decimal:2', 'is_active'=>'boolean', 'images'=>'array', 'mandatory_fields'=>'array', 'duration_minutes'=>'integer', 'break_minutes'=>'integer', 'sort_order'=>'integer'];
    public function appointments() { return $this->hasMany(Appointment::class); }
    public function consentTemplate() { return $this->belongsTo(ConsentTemplate::class); }
    public function categories() { return $this->belongsToMany(AppointmentCategory::class, 'appointment_category_appointment_type'); }
}