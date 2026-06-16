<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')
            ->where('key', 'email_verification')
            ->update([
                'body_html'  => '<h2>Hi {username},</h2><p>Please verify your email address by clicking the button below.</p><p><a href="{verification_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">Verify Email</a></p><p>This link is valid for one-time use only. If you did not create an account, you can safely ignore this email.</p>',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->where('key', 'email_verification')
            ->update([
                'body_html'  => '<h2>Hi {username},</h2><p>Please verify your email address by clicking the button below.</p><p><a href="{verification_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">Verify Email</a></p><p>This link expires in 60 minutes.</p>',
                'updated_at' => now(),
            ]);
    }
};
