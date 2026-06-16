<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50); // course, appointment_type, event, resource
            $table->unsignedBigInteger('item_id')->nullable(); // null = all of this type
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_items');
    }
};
