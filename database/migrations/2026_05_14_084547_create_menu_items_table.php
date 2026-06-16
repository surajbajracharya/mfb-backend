<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('title');
            $table->string('type')->default('custom'); // custom, course, event, resource, appointment_type, category
            $table->unsignedBigInteger('item_id')->nullable(); // FK to actual item
            $table->string('url')->nullable();       // for custom links
            $table->string('target')->default('_self'); // _self or _blank
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('menu_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
