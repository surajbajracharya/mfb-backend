<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandingPage extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'company_id',
        'title',
        'slug',
        'hero_image',
        'excerpt',
        'content',
        'meta_title',
        'meta_description',
        'og_title',
        'og_description',
        'og_image',
        'canonical_url',
        'robots',
        'head_code',
        'body_start_code',
        'body_end_code',
        'button_text',
        'button_url',
        'status',
        'show_header',
        'show_footer',
    ];

    protected $casts = [
        'status'      => 'string',
        'show_header' => 'boolean',
        'show_footer' => 'boolean',
        'content'     => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
