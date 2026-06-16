<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('settings')->insert([
            // General
            ['key' => 'site_name',           'value' => 'Meditation for Beginners',                            'created_at' => $now, 'updated_at' => $now],
            ['key' => 'site_tagline',        'value' => 'Holistic Meditation & Wellness',            'created_at' => $now, 'updated_at' => $now],
            ['key' => 'site_description',    'value' => null,                                        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'timezone',            'value' => 'UTC',                                       'created_at' => $now, 'updated_at' => $now],
            ['key' => 'language',            'value' => 'en',                                        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'maintenance_mode',    'value' => '0',                                         'created_at' => $now, 'updated_at' => $now],
            // SMTP
            ['key' => 'mail_host',           'value' => 'smtp.mailpit.test',                        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'mail_port',           'value' => '1025',                                      'created_at' => $now, 'updated_at' => $now],
            ['key' => 'mail_encryption',     'value' => 'tls',                                       'created_at' => $now, 'updated_at' => $now],
            ['key' => 'mail_username',       'value' => null,                                        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'mail_password',       'value' => null,                                        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'mail_from_address',   'value' => 'noreply@meditationforbeginners.com',                 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'mail_from_name',      'value' => 'Meditation for Beginners',                            'created_at' => $now, 'updated_at' => $now],
            // Theme
            ['key' => 'primary_color',       'value' => '#6366f1',                                   'created_at' => $now, 'updated_at' => $now],
            ['key' => 'secondary_color',     'value' => '#8b5cf6',                                   'created_at' => $now, 'updated_at' => $now],
            ['key' => 'accent_color',        'value' => '#06b6d4',                                   'created_at' => $now, 'updated_at' => $now],
            ['key' => 'font_family',         'value' => 'Inter',                                     'created_at' => $now, 'updated_at' => $now],
            ['key' => 'footer_text',         'value' => null,                                        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'custom_css',          'value' => null,                                        'created_at' => $now, 'updated_at' => $now],
            // Company
            ['key' => 'company_name',        'value' => 'Meditation for Beginners',                            'created_at' => $now, 'updated_at' => $now],
            ['key' => 'company_logo',        'value' => null,                                        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'company_favicon',     'value' => null,                                        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'company_email',       'value' => 'hello@meditationforbeginners.com',                   'created_at' => $now, 'updated_at' => $now],
            ['key' => 'company_phone',       'value' => null,                                        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'company_address',     'value' => null,                                        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'company_website',     'value' => 'https://meditationforbeginners.com',                 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'social_facebook',     'value' => null,                                        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'social_twitter',      'value' => null,                                        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'social_instagram',    'value' => null,                                        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'social_youtube',      'value' => null,                                        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'social_linkedin',     'value' => null,                                        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'copyright_text',      'value' => '© 2026 Meditation for Beginners. All rights reserved.', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
