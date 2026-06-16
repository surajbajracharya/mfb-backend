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
        Schema::table('challenge_day_progress', function (Blueprint $table) {
            $table->tinyInteger('mood')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('challenge_day_progress', function (Blueprint $table) {
            $table->dropColumn('mood');
        });
    }
};
