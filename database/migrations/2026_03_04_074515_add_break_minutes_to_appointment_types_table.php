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
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->unsignedInteger('break_minutes')->default(0)->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->dropColumn('break_minutes');
        });
    }
};
