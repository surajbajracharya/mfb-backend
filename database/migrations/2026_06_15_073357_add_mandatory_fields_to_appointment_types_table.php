<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->json('mandatory_fields')->nullable()->after('consent_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->dropColumn('mandatory_fields');
        });
    }
};
