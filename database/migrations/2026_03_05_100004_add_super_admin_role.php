<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create super_admin role (guard: web, matching Spatie default)
        $existingRole = DB::table('roles')->where('name', 'super_admin')->first();
        if (!$existingRole) {
            $roleId = DB::table('roles')->insertGetId([
                'name'       => 'super_admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $roleId = $existingRole->id;
        }

        // 2. Find the master admin user
        $user = DB::table('users')->where('email', 'admin@meditationforbeginners.com')->first();
        if (!$user) return;

        // 3. Assign super_admin role
        DB::table('model_has_roles')->insertOrIgnore([
            'role_id'    => $roleId,
            'model_id'   => $user->id,
            'model_type' => 'App\\Models\\User',
        ]);

        // 4. Remove old 'admin' role from this user (they are now super_admin, not company admin)
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        if ($adminRole) {
            DB::table('model_has_roles')
                ->where('role_id', $adminRole->id)
                ->where('model_id', $user->id)
                ->where('model_type', 'App\\Models\\User')
                ->delete();
        }
    }

    public function down(): void
    {
        $user = DB::table('users')->where('email', 'admin@meditationforbeginners.com')->first();
        if (!$user) return;

        // Restore admin role
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        if ($adminRole) {
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id'    => $adminRole->id,
                'model_id'   => $user->id,
                'model_type' => 'App\\Models\\User',
            ]);
        }

        // Remove super_admin role
        $superRole = DB::table('roles')->where('name', 'super_admin')->first();
        if ($superRole) {
            DB::table('model_has_roles')
                ->where('role_id', $superRole->id)
                ->where('model_id', $user->id)
                ->delete();
            DB::table('roles')->where('name', 'super_admin')->delete();
        }
    }
};
