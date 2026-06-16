<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class MenuItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'menu_id', 'parent_id', 'title', 'type', 'item_id',
        'url', 'target', 'sort_order', 'is_mega', 'company_id',
    ];

    protected $casts = ['sort_order' => 'integer', 'item_id' => 'integer', 'is_mega' => 'boolean'];

    public function children()
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    public function parent()
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}
