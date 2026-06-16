<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('intro_video')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('type', ['individual', 'membership'])->default('individual');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->string('level')->nullable();
            $table->string('language')->default('en');
            $table->integer('duration_hours')->default(0);
            $table->boolean('has_certificate')->default(false);
            $table->json('what_you_learn')->nullable();
            $table->json('requirements')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('courses'); }
};