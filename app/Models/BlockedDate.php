<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class BlockedDate extends Model
{
    use BelongsToCompany;

    protected $fillable = ['date', 'company_id'];

    public function scopeForMonth($query, string $month)
    {
        return $query->where('date', 'like', $month . '%');
    }
}
