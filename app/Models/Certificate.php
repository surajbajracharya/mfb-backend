<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class Certificate extends Model
{
    use BelongsToCompany;

    protected $fillable = ['user_id', 'course_id', 'certificate_number', 'issued_at', 'company_id', 'pdf_path', 'first_downloaded_at'];

    protected $casts = ['issued_at' => 'datetime', 'first_downloaded_at' => 'datetime'];

    public function user()   { return $this->belongsTo(User::class); }
    public function course() { return $this->belongsTo(Course::class); }
}
