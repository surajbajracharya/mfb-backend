<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add schema_markup to tables that already have meta_title/og_image
        foreach (['courses', 'events', 'resources', 'appointment_types'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->text('schema_markup')->nullable()->after('og_image');
            });
        }

        // Challenges had no SEO fields at all — add all of them
        Schema::table('challenges', function (Blueprint $table) {
            $table->string('meta_title', 120)->nullable()->after('sort_order');
            $table->string('meta_description', 320)->nullable()->after('meta_title');
            $table->string('og_image', 500)->nullable()->after('meta_description');
            $table->text('schema_markup')->nullable()->after('og_image');
        });
    }

    public function down(): void
    {
        foreach (['courses', 'events', 'resources', 'appointment_types'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('schema_markup');
            });
        }
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description', 'og_image', 'schema_markup']);
        });
    }
};
