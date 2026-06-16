<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToCompany;

class ConsentTemplate extends Model
{
    use SoftDeletes, BelongsToCompany;

    protected $fillable = ['name', 'disclaimer_html', 'fields', 'company_id'];
    protected $casts = ['fields' => 'array'];

    public function appointmentTypes()
    {
        return $this->hasMany(AppointmentType::class);
    }
}
