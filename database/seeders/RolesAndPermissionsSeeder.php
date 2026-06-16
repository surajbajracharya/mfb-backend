<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Access
            'access admin panel',
            // Courses
            'view courses', 'create courses', 'edit courses', 'delete courses', 'publish courses',
            // Challenges
            'view challenges', 'create challenges', 'edit challenges', 'delete challenges',
            // Reviews
            'view reviews', 'moderate reviews', 'delete reviews',
            // Categories
            'view categories', 'create categories', 'edit categories', 'delete categories',
            // Resources
            'view resources', 'create resources', 'edit resources', 'delete resources',
            // Enrollments
            'view enrollments', 'manage enrollments',
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
            // Users / Customers
            'view users', 'create users', 'edit users', 'delete users',
            // Orders
            'view orders', 'manage orders', 'refund orders',
            // Plans
            'view plans', 'create plans', 'edit plans', 'delete plans',
            // Landing Pages
            'view landing-pages', 'create landing-pages', 'edit landing-pages', 'delete landing-pages', 'publish landing-pages',
            // Pages
            'view pages', 'create pages', 'edit pages', 'delete pages',
            // Menus
            'view menus', 'create menus', 'edit menus', 'delete menus',
            // Certificates
            'view certificates', 'edit certificate-template',
            // Email Templates
            'view email-templates', 'edit email-templates',
            // Settings
            'view settings', 'edit settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::where('guard_name', 'web')->get());

        $instructorRole = Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
        $instructorRole->syncPermissions([
            'access admin panel',
            'view courses', 'create courses', 'edit courses', 'publish courses',
            'view challenges', 'create challenges', 'edit challenges',
            'view enrollments',
            'view appointments', 'manage appointments',
            'view resources', 'create resources', 'edit resources',
            'view orders',
        ]);

        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $userRole->syncPermissions([
            'view courses', 'view events', 'view resources',
            'view enrollments', 'view appointments', 'view orders',
        ]);
    }
}
