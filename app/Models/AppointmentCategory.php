<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class AppointmentCategory extends Model {
    use BelongsToCompany;
    protected $fillable = ['name', 'company_id'];

    public function appointmentTypes() {
        return $this->belongsToMany(AppointmentType::class, 'appointment_category_appointment_type');
    }
}
