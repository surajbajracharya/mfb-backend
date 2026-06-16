<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    // Roles excluded from the admin roles UI — 'user' is for website customers, not admin panel users
    private static array $excludedFromAdminUI = ['super_admin', 'user'];
    private static array $systemRoles = ['admin', 'instructor'];

    /** All permissions grouped by module for the UI. */
    public static function grouped(): array
    {
        return [
            // Content
            'Courses'         => ['view courses', 'create courses', 'edit courses', 'delete courses', 'publish courses'],
            'Challenges'      => ['view challenges', 'create challenges', 'edit challenges', 'delete challenges'],
            'Reviews'         => ['view reviews', 'moderate reviews', 'delete reviews'],
            'Categories'      => ['view categories', 'create categories', 'edit categories', 'delete categories'],
            'Resources'       => ['view resources', 'create resources', 'edit resources', 'delete resources'],
            'Enrollments'     => ['view enrollments', 'manage enrollments'],
            // Events
            'Events'          => ['view events', 'create events', 'edit events', 'delete events', 'publish events'],
            // Appointments
            'Appointments'    => ['view appointments', 'create appointments', 'edit appointments', 'delete appointments', 'manage appointments'],
            'Appt. Types'     => ['view appointment-types', 'create appointment-types', 'edit appointment-types', 'delete appointment-types'],
            'Availability'    => ['manage availability'],
            'Consent Forms'   => ['view consent-forms', 'create consent-forms', 'edit consent-forms', 'delete consent-forms'],
            // Commerce
            'Customers'       => ['view users', 'create users', 'edit users', 'delete users'],
            'Orders'          => ['view orders', 'manage orders', 'refund orders'],
            'Plans'           => ['view plans', 'create plans', 'edit plans', 'delete plans'],
            // Site
            'Landing Pages'   => ['view landing-pages', 'create landing-pages', 'edit landing-pages', 'delete landing-pages', 'publish landing-pages'],
            'Pages'           => ['view pages', 'create pages', 'edit pages', 'delete pages'],
            'Menus'           => ['view menus', 'create menus', 'edit menus', 'delete menus'],
            // System
            'Certificates'    => ['view certificates', 'edit certificate-template'],
            'Email Templates' => ['view email-templates', 'edit email-templates'],
            'Settings'        => ['view settings', 'edit settings'],
        ];
    }

    /** GET /api/v1/superadmin/permissions-grouped */
    public function permissionsGrouped(): JsonResponse
    {
        return response()->json(self::grouped());
    }

    /** GET /api/v1/superadmin/roles */
    public function index(TenantContext $tenant): JsonResponse
    {
        $query = Role::with('permissions')->whereNotIn('name', self::$excludedFromAdminUI);

        if ($tenant->companyId()) {
            // Company selected: show ONLY roles belonging to that company
            $query->where('company_id', $tenant->companyId());
        }
        // Global view: show all roles (no extra filter)

        $roles = $query->orderByRaw('company_id IS NULL DESC')->orderBy('name')->get()
            ->map(fn ($role) => $this->formatRole($role));

        return response()->json($roles);
    }

    /** POST /api/v1/superadmin/roles */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100', 'unique:roles,name'],
            'permissions'   => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'company_id'    => ['required', 'integer', 'exists:companies,id'],
        ]);

        $role = Role::create([
            'name'       => $data['name'],
            'guard_name' => 'web',
            'company_id' => $data['company_id'],
        ]);

        $perms = array_unique(array_merge($data['permissions'], ['access admin panel']));
        $role->syncPermissions($perms);

        return response()->json($this->formatRole($role->load('permissions')), 201);
    }

    /** PUT /api/v1/superadmin/roles/{role} */
    public function update(Request $request, Role $role): JsonResponse
    {
        if (in_array($role->name, self::$excludedFromAdminUI)) {
            return response()->json(['message' => 'Cannot modify this system role.'], 403);
        }

        $isSystem = in_array($role->name, self::$systemRoles);

        $data = $request->validate([
            'name'          => ['sometimes', 'string', 'max:100', 'unique:roles,name,' . $role->id],
            'permissions'   => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if (!$isSystem && isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if (isset($data['permissions'])) {
            $perms = array_unique(array_merge($data['permissions'], ['access admin panel']));
            $role->syncPermissions($perms);
        }

        return response()->json($this->formatRole($role->load('permissions')));
    }

    /** DELETE /api/v1/superadmin/roles/{role} */
    public function destroy(Role $role): JsonResponse
    {
        if (in_array($role->name, self::$excludedFromAdminUI)) {
            return response()->json(['message' => 'Cannot delete this system role.'], 403);
        }

        $userCount = \DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', \App\Models\User::class)
            ->count();

        if ($userCount > 0) {
            return response()->json(['message' => 'Cannot delete a role that has assigned users. Reassign them first.'], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }

    private function formatRole(Role $role): array
    {
        return [
            'id'          => $role->id,
            'name'        => $role->name,
            'company_id'  => $role->company_id,
            'permissions' => $role->permissions->pluck('name'),
            'users_count' => \DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', \App\Models\User::class)
                ->count(),
        ];
    }
}
