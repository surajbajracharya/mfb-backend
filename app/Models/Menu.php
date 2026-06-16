<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class Menu extends Model
{
    use BelongsToCompany;

    protected $fillable = ['name', 'slug', 'location', 'company_id'];

    public function items()
    {
        return $this->hasMany(MenuItem::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    public function allItems()
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }
}
