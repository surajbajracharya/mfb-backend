<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // One certificate per user per course — enforced at DB level
            $table->unique(['user_id', 'course_id'], 'certificates_user_course_unique');
            // Certificate number is globally unique
            $table->unique('certificate_number', 'certificates_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropUnique('certificates_user_course_unique');
            $table->dropUnique('certificates_number_unique');
        });
    }
};
