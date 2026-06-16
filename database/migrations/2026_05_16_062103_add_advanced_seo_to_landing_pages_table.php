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
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->string('og_title')->nullable()->after('meta_description');
            $table->text('og_description')->nullable()->after('og_title');
            $table->string('og_image')->nullable()->after('og_description');
            $table->string('canonical_url')->nullable()->after('og_image');
            $table->string('robots')->nullable()->default('index,follow')->after('canonical_url');
            $table->longText('head_code')->nullable()->after('robots');
            $table->longText('body_start_code')->nullable()->after('head_code');
            $table->longText('body_end_code')->nullable()->after('body_start_code');
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropColumn(['og_title','og_description','og_image','canonical_url','robots','head_code','body_start_code','body_end_code']);
        });
    }
};
