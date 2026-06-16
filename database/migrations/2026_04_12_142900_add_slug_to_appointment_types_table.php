<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add slug column if it doesn't already exist
        if (!Schema::hasColumn('appointment_types', 'slug')) {
            Schema::table('appointment_types', function (Blueprint $table) {
                $table->string('slug')->nullable()->after('title');
            });
        }

        // Generate slugs for any records missing one
        DB::table('appointment_types')->whereNull('slug')->orWhere('slug', '')->get(['id', 'title'])
            ->each(function ($type) {
                $base = Str::slug($type->title) ?: 'appointment-' . $type->id;
                $slug = $base;
                $i = 1;
                while (DB::table('appointment_types')->where('slug', $slug)->where('id', '!=', $type->id)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                DB::table('appointment_types')->where('id', $type->id)->update(['slug' => $slug]);
            });

        // Ensure not nullable
        DB::statement('ALTER TABLE appointment_types MODIFY COLUMN slug VARCHAR(255) NOT NULL');

        // Add unique index if not present
        $indexes = collect(DB::select("SHOW INDEX FROM appointment_types WHERE Key_name = 'appointment_types_slug_unique'"));
        if ($indexes->isEmpty()) {
            Schema::table('appointment_types', function (Blueprint $table) {
                $table->unique('slug');
            });
        }
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
