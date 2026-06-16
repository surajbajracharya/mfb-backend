<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class PlanItem extends Model
{
    use BelongsToCompany;

    protected $fillable = ['plan_id', 'company_id', 'type', 'item_id'];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
