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
        Schema::create('appointment_category_appointment_type', function (Blueprint $table) {
            $table->unsignedBigInteger('appointment_category_id');
            $table->unsignedBigInteger('appointment_type_id');
            $table->primary(['appointment_category_id', 'appointment_type_id']);
            $table->foreign('appointment_category_id', 'appt_cat_pivot_cat_fk')->references('id')->on('appointment_categories')->cascadeOnDelete();
            $table->foreign('appointment_type_id', 'appt_cat_pivot_type_fk')->references('id')->on('appointment_types')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_category_appointment_type');
    }
};
