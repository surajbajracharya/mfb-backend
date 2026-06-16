<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /** All admin permissions, grouped by module. */
    public static array $all = [
        // Panel access — required for any staff/admin role
        'access admin panel',

        // Courses
        'view courses', 'create courses', 'edit courses', 'delete courses', 'publish courses',

        // Reviews
        'view reviews', 'moderate reviews', 'delete reviews',

        // Categories
        'view categories', 'create categories', 'edit categories', 'delete categories',

        // Events
        'view events', 'create events', 'edit events', 'delete events', 'publish events',

        // Appointments
        'view appointments', 'create appointments', 'edit appointments', 'delete appointments', 'manage appointments',

        // Appointment Types
        'view appointment-types', 'create appointment-types', 'edit appointment-types', 'delete appointment-types',

        // Availability
        'manage availability',

        // Consent Forms
        'view consent-forms', 'create consent-forms', 'edit consent-forms', 'delete consent-forms',

        // Resources
        'view resources', 'create resources', 'edit resources', 'delete resources',

        // Enrollments
        'view enrollments', 'manage enrollments',

        // Users
        'view users', 'create users', 'edit users', 'delete users', 'manage users',

        // Orders
        'view orders', 'manage orders', 'refund orders',

        // Plans
        'view plans', 'create plans', 'edit plans', 'delete plans', 'manage plans',

        // Certificates
        'view certificates', 'edit certificate-template',

        // Email Templates & Settings
        'view email-templates', 'edit email-templates',

        // Settings
        'view settings', 'edit settings',
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create any missing permissions
        foreach (self::$all as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Give the admin role ALL permissions (including new ones)
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // Give the instructor role panel access + their existing subset
        $instructor = Role::firstWhere('name', 'instructor');
        if ($instructor) {
            $instructor->givePermissionTo('access admin panel');
        }
    }

    public function down(): void
    {
        // Remove only the permissions added by this migration that didn't exist before
        $legacy = [
            'view courses', 'create courses', 'edit courses', 'delete courses', 'publish courses',
            'view enrollments', 'manage enrollments',
            'view appointments', 'create appointments', 'manage appointments',
            'view events', 'create events', 'edit events', 'delete events', 'publish events',
            'view resources', 'create resources', 'edit resources', 'delete resources',
            'view orders', 'manage orders', 'refund orders',
            'view users', 'manage users',
            'view plans', 'manage plans',
        ];
        $toRemove = array_diff(self::$all, $legacy);
        foreach ($toRemove as $perm) {
            Permission::where('name', $perm)->delete();
        }
    }
};
