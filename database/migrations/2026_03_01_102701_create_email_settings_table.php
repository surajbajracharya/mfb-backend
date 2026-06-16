<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name')->default('Meditation for Beginners');
            $table->string('from_name')->default('Meditation for Beginners');
            $table->string('from_email')->default('noreply@meditationforbeginners.com');
            // Header
            $table->string('header_logo')->nullable();         // URL
            $table->string('header_bg_color')->default('#6366f1');
            $table->string('header_text_color')->default('#ffffff');
            $table->string('header_tagline')->nullable();
            // Footer
            $table->longText('footer_html')->nullable();       // Custom HTML footer
            $table->string('footer_bg_color')->default('#f9fafb');
            $table->string('footer_text_color')->default('#6b7280');
            // Social links (JSON: {facebook, twitter, instagram, linkedin})
            $table->json('social_links')->nullable();
            $table->timestamps();
        });

        DB::table('email_settings')->insert([
            'site_name'          => 'Meditation for Beginners',
            'from_name'          => 'Meditation for Beginners',
            'from_email'         => 'noreply@meditationforbeginners.com',
            'header_bg_color'    => '#6366f1',
            'header_text_color'  => '#ffffff',
            'header_tagline'     => 'Holistic Meditation & Wellness',
            'footer_html'        => '<p>© ' . date('Y') . ' Meditation for Beginners. All rights reserved.</p><p>You received this email because you registered on our platform.</p>',
            'footer_bg_color'    => '#f9fafb',
            'footer_text_color'  => '#6b7280',
            'social_links'       => json_encode(['facebook' => '', 'twitter' => '', 'instagram' => '', 'linkedin' => '']),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_settings');
    }
};
