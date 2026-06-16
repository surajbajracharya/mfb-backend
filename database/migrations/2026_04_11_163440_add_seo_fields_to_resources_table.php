<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->string('meta_title', 120)->nullable()->after('content');
            $table->string('meta_description', 320)->nullable()->after('meta_title');
            $table->string('og_image', 500)->nullable()->after('meta_description');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description', 'og_image']);
        });
    }
};
