<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users',
        'categories',
        'courses',
        'course_sections',
        'course_lectures',
        'plans',
        'orders',
        'order_items',
        'enrollments',
        'course_progress',
        'course_reviews',
        'appointment_types',
        'appointments',
        'availability_schedules',
        'events',
        'event_tickets',
        'resources',
        'lecture_notes',
        'certificates',
        'certificate_templates',
        'email_templates',
        'email_settings',
        'consent_templates',
        'settings',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('company_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('companies')
                  ->nullOnDelete();
                $t->index('company_id');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropForeign([$table . '_company_id_foreign'] ?? ['company_id']);
                $t->dropIndex(['company_id']);
                $t->dropColumn('company_id');
            });
        }
    }
};
