<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create the Meditation for Beginners company record
        $companyId = DB::table('companies')->insertGetId([
            'name'         => 'Meditation for Beginners',
            'slug'         => 'meditationforbeginners',
            'domain'       => 'meditationforbeginners.com',
            'api_domain'   => '127.0.0.1:8002',
            'admin_domain' => 'localhost:3000',
            'is_active'    => 1,
            'plan_type'    => 'standard',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // 2. Assign all existing content to this company
        $contentTables = [
            'categories', 'courses', 'course_sections', 'course_lectures',
            'plans', 'orders', 'order_items', 'enrollments', 'course_progress',
            'course_reviews', 'appointment_types', 'appointments',
            'availability_schedules', 'events', 'event_tickets',
            'resources', 'lecture_notes', 'certificates',
        ];
        foreach ($contentTables as $table) {
            DB::table($table)->update(['company_id' => $companyId]);
        }

        // 3. Assign singleton/template tables to the company.
        //    The existing rows (company_id = NULL) become the "global defaults"
        //    used when seeding new companies. We COPY them first, then update.

        // email_templates: copy nulls as global defaults, assign originals to Meditation for Beginners
        DB::table('email_templates')->update(['company_id' => $companyId]);

        // email_settings: copy null as global default, assign original to Meditation for Beginners
        DB::table('email_settings')->update(['company_id' => $companyId]);

        // certificate_templates: assign to Meditation for Beginners
        DB::table('certificate_templates')->update(['company_id' => $companyId]);

        // consent_templates: assign to Meditation for Beginners
        DB::table('consent_templates')->update(['company_id' => $companyId]);

        // settings: assign to Meditation for Beginners
        DB::table('settings')->update(['company_id' => $companyId]);

        // 4. Users: all except the super admin get Meditation for Beginners company
        DB::table('users')
            ->where('email', '!=', 'admin@meditationforbeginners.com')
            ->update(['company_id' => $companyId]);
        // super admin user stays company_id = NULL
    }

    public function down(): void
    {
        // Reset all company_id columns to null and remove the meditationforbeginners company
        $tables = [
            'users', 'categories', 'courses', 'course_sections', 'course_lectures',
            'plans', 'orders', 'order_items', 'enrollments', 'course_progress',
            'course_reviews', 'appointment_types', 'appointments',
            'availability_schedules', 'events', 'event_tickets',
            'resources', 'lecture_notes', 'certificates', 'certificate_templates',
            'email_templates', 'email_settings', 'consent_templates', 'settings',
        ];
        foreach ($tables as $table) {
            DB::table($table)->update(['company_id' => null]);
        }
        DB::table('companies')->where('slug', 'meditationforbeginners')->delete();
    }
};
