<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\UserPlanSubscription;
use App\Services\EmailService;
use App\Services\TenantContext;
use Illuminate\Console\Command;

class ExpirePlanSubscriptions extends Command
{
    protected $signature   = 'subscriptions:expire';
    protected $description = 'Mark plan subscriptions as expired when their expires_at has passed';

    public function handle(): void
    {
        $expiring = UserPlanSubscription::withoutCompanyScope()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with(['user', 'plan'])
            ->get();

        foreach ($expiring as $sub) {
            if ($sub->user && $sub->company_id) {
                $company = Company::find($sub->company_id);
                if ($company) {
                    app(TenantContext::class)->setCompany($company);
                }
            }

            $frontendUrl = rtrim(config('app.frontend_url', url('/')), '/');

            if ($sub->user) {
                EmailService::send($sub->user->email, 'subscription_expired', [
                    '{username}'      => $sub->user->name,
                    '{email}'         => $sub->user->email,
                    '{plan_name}'     => $sub->plan?->title ?? 'Subscription',
                    '{dashboard_url}' => $frontendUrl . '/dashboard',
                    '{site_name}'     => config('app.name'),
                ]);
            }

            $sub->update(['status' => 'expired']);
        }

        $this->info("Marked {$expiring->count()} subscription(s) as expired.");
    }
}
