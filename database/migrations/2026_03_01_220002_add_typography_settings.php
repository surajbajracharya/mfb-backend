<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $now = now();
        $defaults = [
            ['key' => 'heading_font',          'value' => 'same',   'created_at' => $now, 'updated_at' => $now],
            ['key' => 'heading_font_weight',   'value' => '700',    'created_at' => $now, 'updated_at' => $now],
            ['key' => 'heading_font_size_h1',  'value' => '36',     'created_at' => $now, 'updated_at' => $now],
            ['key' => 'heading_font_size_h2',  'value' => '28',     'created_at' => $now, 'updated_at' => $now],
            ['key' => 'heading_font_size_h3',  'value' => '22',     'created_at' => $now, 'updated_at' => $now],
            ['key' => 'heading_letter_spacing','value' => '-0.02',  'created_at' => $now, 'updated_at' => $now],
            ['key' => 'heading_text_transform','value' => 'none',   'created_at' => $now, 'updated_at' => $now],
            ['key' => 'body_font_size',        'value' => '16',     'created_at' => $now, 'updated_at' => $now],
            ['key' => 'body_font_weight',      'value' => '400',    'created_at' => $now, 'updated_at' => $now],
            ['key' => 'body_line_height',      'value' => '1.6',    'created_at' => $now, 'updated_at' => $now],
            ['key' => 'body_letter_spacing',   'value' => '0',      'created_at' => $now, 'updated_at' => $now],
        ];
        // Only insert keys that don't already exist
        foreach ($defaults as $row) {
            DB::table('settings')->updateOrInsert(['key' => $row['key']], $row);
        }
    }
    public function down(): void {
        $keys = ['heading_font','heading_font_weight','heading_font_size_h1','heading_font_size_h2',
                 'heading_font_size_h3','heading_letter_spacing','heading_text_transform',
                 'body_font_size','body_font_weight','body_line_height','body_letter_spacing'];
        \Illuminate\Support\Facades\DB::table('settings')->whereIn('key', $keys)->delete();
    }
};
