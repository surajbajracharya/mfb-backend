<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendPendingWelcomeEmails extends Command
{
    protected $signature   = 'customers:send-welcome-emails';
    protected $description = 'Send welcome emails to CSV-imported customers and clear the queue';

    public function handle(): void
    {
        $pending = DB::table('pending_welcome_emails')->get();

        if ($pending->isEmpty()) {
            return;
        }

        $frontendUrl = rtrim(config('app.frontend_url', url('/')), '/');
        $loginUrl    = $frontendUrl . '/login';
        $siteName    = \App\Models\Setting::getValue('site_name') ?? config('app.name');

        foreach ($pending as $item) {
            $user = User::withoutGlobalScope('company')->find($item->user_id);

            if (!$user) {
                DB::table('pending_welcome_emails')->where('id', $item->id)->delete();
                continue;
            }

            EmailService::send($user->email, 'admin_user_created', [
                '{username}'      => $user->name,
                '{email}'         => $user->email,
                '{temp_password}' => $item->plain_password,
                '{login_url}'     => $loginUrl,
                '{site_name}'     => $siteName,
            ]);

            // Delete immediately after sending — never repeats
            DB::table('pending_welcome_emails')->where('id', $item->id)->delete();

            $this->line("Sent welcome email to: {$user->email}");
        }
    }
}
