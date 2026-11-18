<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::where(
            'guard_name',
            'staff'
        )->get();

        return response()->json([
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:permissions,name',
            ],
        ]);

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => 'staff',
        ]);

        return response()->json([
            'message' => 'Permission created successfully.',
            'permission' => $permission,
        ], 201);
    }

    public function assign(
        Request $request,
        Staff $staff
    ): JsonResponse {
        $validated = $request->validate([
            'permission' => [
                'required',
                'string',
                'exists:permissions,name',
            ],
        ]);

        $staff->givePermissionTo(
            $validated['permission']
        );

        return response()->json([
            'message' => 'Permission assigned successfully.',
            'permissions' => $staff->getAllPermissions()
                ->pluck('name')
                ->values(),
        ]);
    }

    public function revoke(
        Request $request,
        Staff $staff
    ): JsonResponse {
        $validated = $request->validate([
            'permission' => [
                'required',
                'string',
                'exists:permissions,name',
            ],
        ]);

        $staff->revokePermissionTo(
            $validated['permission']
        );

        return response()->json([
            'message' => 'Permission revoked successfully.',
            'permissions' => $staff->getAllPermissions()
                ->pluck('name')
                ->values(),
        ]);
    }

    public function staffPermissions(
        Staff $staff
    ): JsonResponse {
        return response()->json([
            'permissions' => $staff->getDirectPermissions()
                ->pluck('name')
                ->values(),
        ]);
    }

    public function roles(): JsonResponse
    {
        $this->ensureCan('role.view');

        $roles = Role::where('guard_name', 'staff')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values(),
            ]);

        return response()->json(['roles' => $roles]);
    }

    public function permissions(): JsonResponse
    {
        $this->ensureCan('permission.view');

        return response()->json([
            'permissions' => Permission::where('guard_name', 'staff')
                ->orderBy('name')
                ->pluck('name')
                ->values(),
        ]);
    }

    private function ensureCan(string $permission): void
    {
        /** @var Staff $staff */
        $staff = auth('staff')->user();

        abort_unless(
            $staff->hasRole('super-admin') || $staff->getAllPermissions()->contains('name', $permission),
            403,
            'You are not allowed to do that.'
        );
    }
}
