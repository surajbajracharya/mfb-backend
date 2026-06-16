<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('hero_image')->nullable();
            $table->string('organizer_name')->nullable();
            $table->string('organizer_email')->nullable();
            $table->string('organizer_phone')->nullable();
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            $table->string('venue_phone')->nullable();
            $table->string('meeting_link')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone')->default('Australia/Sydney');
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('capacity');
            $table->integer('tickets_sold')->default(0);
            $table->enum('status', ['draft', 'published', 'cancelled', 'completed'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
