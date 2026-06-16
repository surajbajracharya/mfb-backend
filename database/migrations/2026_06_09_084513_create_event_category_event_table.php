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
        Schema::create('event_category_event', function (Blueprint $table) {
            $table->unsignedBigInteger('event_category_id');
            $table->unsignedBigInteger('event_id');
            $table->primary(['event_category_id', 'event_id']);
            $table->foreign('event_category_id', 'evt_cat_pivot_cat_fk')->references('id')->on('event_categories')->cascadeOnDelete();
            $table->foreign('event_id', 'evt_cat_pivot_evt_fk')->references('id')->on('events')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_category_event');
    }
};
