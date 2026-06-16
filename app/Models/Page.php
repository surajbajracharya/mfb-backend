<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'title', 'slug', 'template', 'status',
        'content', 'sidebar_left', 'sidebar_right',
        'featured_image', 'gallery',
        'meta_title', 'meta_description', 'meta_keywords',
        'og_image', 'canonical_url', 'robots', 'schema_markup',
    ];

    protected $casts = [
        'gallery' => 'array',
    ];
}
