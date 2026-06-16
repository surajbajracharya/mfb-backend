<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('meta_title', 120)->nullable()->after('description');
            $table->string('meta_description', 320)->nullable()->after('meta_title');
            $table->string('og_image', 500)->nullable()->after('meta_description');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('meta_title', 120)->nullable()->after('description');
            $table->string('meta_description', 320)->nullable()->after('meta_title');
            $table->string('og_image', 500)->nullable()->after('meta_description');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description', 'og_image']);
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description', 'og_image']);
        });
    }
};
