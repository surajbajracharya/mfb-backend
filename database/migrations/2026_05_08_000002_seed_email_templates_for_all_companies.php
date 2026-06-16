<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $companies = DB::table('companies')->pluck('id')->toArray();

        // Fetch the canonical set of templates (company 1 is the source of truth)
        $source = DB::table('email_templates')
            ->where('company_id', $companies[0] ?? 1)
            ->get();

        foreach ($companies as $companyId) {
            foreach ($source as $tpl) {
                $exists = DB::table('email_templates')
                    ->where('key', $tpl->key)
                    ->where('company_id', $companyId)
                    ->exists();

                if (!$exists) {
                    DB::table('email_templates')->insert([
                        'key'                  => $tpl->key,
                        'name'                 => $tpl->name,
                        'subject'              => $tpl->subject,
                        'body_html'            => $tpl->body_html,
                        'available_shortcodes' => $tpl->available_shortcodes,
                        'is_active'            => $tpl->is_active,
                        'company_id'           => $companyId,
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ]);
                }
            }
        }

        // Ensure every company has an email_settings record
        foreach ($companies as $companyId) {
            $exists = DB::table('email_settings')->where('company_id', $companyId)->exists();
            if (!$exists) {
                $siteName = DB::table('settings')
                    ->where('key', 'site_name')
                    ->where('company_id', $companyId)
                    ->value('value') ?? config('app.name');

                $fromEmail = DB::table('settings')
                    ->where('key', 'mail_from_address')
                    ->where('company_id', $companyId)
                    ->value('value') ?? config('mail.from.address');

                DB::table('email_settings')->insert([
                    'site_name'         => $siteName,
                    'from_name'         => $siteName,
                    'from_email'        => $fromEmail,
                    'header_bg_color'   => '#6366f1',
                    'header_text_color' => '#ffffff',
                    'company_id'        => $companyId,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Remove templates for companies beyond the first
        $firstCompanyId = DB::table('companies')->orderBy('id')->value('id');
        if ($firstCompanyId) {
            DB::table('email_templates')
                ->where('company_id', '!=', $firstCompanyId)
                ->delete();
        }
    }
};
