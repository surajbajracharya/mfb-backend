<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class ResourceView extends Model
{
    use BelongsToCompany;

    protected $fillable = ['user_id', 'resource_id', 'company_id', 'viewed_at'];

    protected $casts = ['viewed_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function resource() { return $this->belongsTo(Resource::class); }
}
