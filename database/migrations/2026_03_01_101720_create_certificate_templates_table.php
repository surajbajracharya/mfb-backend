<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('logo_left')->nullable();
            $table->string('logo_right')->nullable();
            $table->string('background_color', 20)->default('#ffffff');
            $table->string('border_color', 20)->default('#6366f1');
            $table->integer('border_width')->default(6);
            $table->string('title')->default('Certificate of Completion');
            $table->string('title_color', 20)->default('#4f46e5');
            $table->longText('body_html');
            $table->text('footer_text')->nullable();
            $table->string('signature_image')->nullable();
            $table->string('signature_label')->default('Authorized Signature');
            $table->boolean('show_certificate_number')->default(true);
            $table->boolean('show_date')->default(true);
            $table->timestamps();
        });

        // Seed one default global template
        DB::table('certificate_templates')->insert([
            'body_html'    => '<p style="text-align:center;font-size:16px;color:#555555;">This is to certify that</p><p style="text-align:center;font-size:36px;font-weight:bold;color:#1f2937;font-style:italic;margin:12px 0;">{{student_name}}</p><p style="text-align:center;font-size:16px;color:#555555;margin-bottom:8px;">has successfully completed the course</p><p style="text-align:center;font-size:24px;font-weight:bold;color:#4f46e5;margin-bottom:16px;">{{course_title}}</p><p style="text-align:center;font-size:14px;color:#777777;">with flying colors and dedication.</p>',
            'footer_text'  => 'Meditation for Beginners · Holistic Meditation & Wellness',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');
    }
};
