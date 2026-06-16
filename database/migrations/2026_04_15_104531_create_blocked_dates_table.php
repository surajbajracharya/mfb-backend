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
        Schema::create('blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->timestamps();
            $table->unique(['date', 'company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_dates');
    }
};
