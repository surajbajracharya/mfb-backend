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
            $table->foreignId('consent_template_id')
                  ->nullable()
                  ->constrained('consent_templates')
                  ->nullOnDelete()
                  ->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->dropForeign(['consent_template_id']);
            $table->dropColumn('consent_template_id');
        });
    }
};
