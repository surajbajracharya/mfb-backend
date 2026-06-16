<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('course_lectures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('course_sections')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['video', 'audio', 'pdf', 'text'])->default('video');
            $table->string('content_url')->nullable();
            $table->integer('duration_seconds')->default(0);
            $table->boolean('is_preview')->default(false);
            $table->json('resources')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('course_lectures'); }
};