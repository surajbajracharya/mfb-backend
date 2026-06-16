<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityVerification extends Model
{
    protected $fillable = ['user_id', 'token', 'id_proof_url', 'status', 'expires_at', 'used_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->status === 'pending'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
