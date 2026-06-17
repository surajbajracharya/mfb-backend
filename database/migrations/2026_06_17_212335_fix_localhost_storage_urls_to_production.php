<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $new = 'https://api.meditationforbeginners.com';

        // Both localhost patterns used at different times during dev
        $oldPatterns = [
            'http://127.0.0.1:8002',
            'http://localhost:8002',
        ];

        // Simple string columns — direct SQL REPLACE
        $columns = [
            ['appointment_types',    'og_image'],
            ['categories',           'image'],
            ['certificate_templates','logo_left'],
            ['certificate_templates','logo_right'],
            ['certificate_templates','signature_image'],
            ['challenge_days',       'audio_url'],
            ['challenge_days',       'image_url'],
            ['challenge_days',       'video_url'],
            ['challenges',           'badge_image'],
            ['challenges',           'og_image'],
            ['challenges',           'thumbnail'],
            ['companies',            'logo'],
            ['course_lectures',      'content_url'],
            ['courses',              'og_image'],
            ['courses',              'thumbnail'],
            ['email_settings',       'header_logo'],
            ['events',               'hero_image'],
            ['events',               'og_image'],
            ['landing_pages',        'hero_image'],
            ['landing_pages',        'og_image'],
            ['pages',                'featured_image'],
            ['pages',                'og_image'],
            ['plans',                'banner'],
            ['plans',                'thumbnail'],
            ['resources',            'file_url'],
            ['resources',            'og_image'],
            ['resources',            'thumbnail'],
            ['users',                'avatar'],
        ];

        foreach ($oldPatterns as $old) {
            foreach ($columns as [$table, $column]) {
                DB::statement(
                    "UPDATE `{$table}` SET `{$column}` = REPLACE(`{$column}`, ?, ?) WHERE `{$column}` LIKE ?",
                    [$old, $new, '%' . $old . '%']
                );
            }
        }

        // appointment_types.images is a JSON array of URLs — needs PHP decode/re-encode
        DB::table('appointment_types')->whereNotNull('images')->orderBy('id')->each(function ($row) use ($oldPatterns, $new) {
            $images = json_decode($row->images, true);
            if (!is_array($images)) {
                return;
            }
            $fixed = array_map(fn($url) => str_replace($oldPatterns, $new, $url), $images);
            if ($fixed !== $images) {
                DB::table('appointment_types')->where('id', $row->id)->update(['images' => json_encode($fixed)]);
            }
        });
    }

    public function down(): void
    {
        // One-way data fix — no rollback needed
    }
};
