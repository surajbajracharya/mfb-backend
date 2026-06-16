<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class AvailabilitySchedule extends Model
{
    use BelongsToCompany;

    protected $fillable = ['date', 'start_time', 'end_time', 'company_id'];

    public function scopeForDate($query, string $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForMonth($query, string $month)
    {
        return $query->where('date', 'like', $month . '%');
    }
}
