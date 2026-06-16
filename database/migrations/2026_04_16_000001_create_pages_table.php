<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('title');
            $table->string('slug')->index();
            $table->enum('template', ['left-sidebar', 'right-sidebar', 'both-sidebars', 'narrow'])->default('narrow');
            $table->string('status')->default('draft');

            // Content areas
            $table->longText('content')->nullable();
            $table->longText('sidebar_left')->nullable();
            $table->longText('sidebar_right')->nullable();

            // Media
            $table->string('featured_image')->nullable();
            $table->json('gallery')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('og_image')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('robots')->default('index,follow');
            $table->text('schema_markup')->nullable();

            $table->timestamps();
            $table->unique(['slug', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
