<?php

namespace App\Services;

use App\Models\UserPlanSubscription;

class PlanAccessService
{
    /**
     * Check whether a user has an active plan subscription that grants
     * access to the given item type + id.
     *
     * type: 'course' | 'appointment_type' | 'event' | 'resource'
     */
    public static function userHasAccess(int $userId, string $type, int $itemId): bool
    {
        $now = now();

        $subscriptions = UserPlanSubscription::with('plan.items')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('started_at', '<=', $now)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->get();

        foreach ($subscriptions as $sub) {
            if ($sub->grantsAccess($type, $itemId)) {
                return true;
            }
        }

        return false;
    }
}
