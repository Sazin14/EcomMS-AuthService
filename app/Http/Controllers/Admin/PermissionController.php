<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * List available permissions.
     */
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

    /**
     * Create a new permission.
     */
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

    /**
     * Give a direct permission to a staff member.
     */
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

    /**
     * Remove a direct permission from a staff member.
     */
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

    /**
     * Show direct permissions assigned to a staff member.
     */
    public function staffPermissions(
        Staff $staff
    ): JsonResponse {
        return response()->json([
            'permissions' => $staff->getDirectPermissions()
                ->pluck('name')
                ->values(),
        ]);
    }
}