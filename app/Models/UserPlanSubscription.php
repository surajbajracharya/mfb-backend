<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class UserPlanSubscription extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'user_id', 'plan_id', 'order_item_id', 'company_id',
        'started_at', 'expires_at', 'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        $now = now();
        return $this->status === 'active'
            && $this->started_at->lte($now)
            && ($this->expires_at === null || $this->expires_at->gt($now));
    }

    /**
     * Check if this subscription grants access to a specific item.
     * type: 'course' | 'appointment_type' | 'event' | 'resource'
     * itemId: the ID of that model
     */
    public function grantsAccess(string $type, int $itemId): bool
    {
        $items = $this->plan->items->where('type', $type);

        // No rows for this type → mode is "all" → grant access
        if ($items->isEmpty()) return true;

        // Sentinel (item_id=0) → specific mode, nothing selected → deny
        if ($items->count() === 1 && $items->first()->item_id === 0) return false;

        // Null item_id → all-access row
        if ($items->whereNull('item_id')->isNotEmpty()) return true;

        // Check if the specific item is listed
        return $items->pluck('item_id')->contains($itemId);
    }
}
