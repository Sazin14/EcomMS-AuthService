<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\AuthService;
use App\Services\StaffManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class StaffController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private StaffManagementService $staffManagementService
    ) {
    }

    public function index(): JsonResponse
    {
        abort_unless(auth('staff')->user()->can('staff.view'), 403, 'You do not have permission [staff.view].');

        $staff = Staff::with('roles')
            ->latest()
            ->paginate(20);

        return response()->json($staff);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:staffs,email',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
            ],

            'role' => [
                'required',
                'string',
                'exists:roles,name',
            ],
        ]);

        $staff = $this->staffManagementService->create(
            actor: auth('staff')->user(),
            data: $validated
        );

        return response()->json([
            'message' => 'Staff created successfully.',
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'roles' => $staff->getRoleNames(),
            ],
        ], 201);
    }

    public function show(Staff $staff): JsonResponse
    {
        /** @var Staff $actor */
        $actor = auth('staff')->user();

        abort_unless($actor->id === $staff->id || $actor->can('staff.view'), 403, 'You do not have permission [staff.view].');

        $staff->load('roles', 'permissions');

        return response()->json([
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'roles' => $staff->getRoleNames(),
                'permissions' => $staff->getAllPermissions()->pluck('name')->values(),
                'direct_permissions' => $staff->getPermissionNames(),
            ],
        ]);
    }

    public function update(
        Request $request,
        Staff $staff
    ): JsonResponse {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('staffs', 'email')
                    ->ignore($staff->id),
            ],
        ]);

        try {
            $this->staffManagementService->updateProfile(
                actor: auth('staff')->user(),
                target: $staff,
                data: $validated
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }

        $staff->refresh();

        return response()->json([
            'message' => 'Staff updated successfully.',
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
            ],
        ]);
    }

    public function destroy(Staff $staff): JsonResponse
    {
        try {
            $this->staffManagementService->delete(
                actor: auth('staff')->user(),
                target: $staff
            );

            return response()->json([
                'message' => 'Staff deleted successfully.',
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function assignRole(
        Request $request,
        Staff $staff
    ): JsonResponse {
        $validated = $request->validate([
            'role' => [
                'required',
                'string',
                'exists:roles,name',
            ],
        ]);

        try {
            $this->staffManagementService->assignRole(
                actor: auth('staff')->user(),
                target: $staff,
                roleName: $validated['role']
            );

            $staff->refresh();

            return response()->json([
                'message' => 'Role assigned successfully.',
                'roles' => $staff->getRoleNames(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function removeRole(
        Request $request,
        Staff $staff
    ): JsonResponse {
        $validated = $request->validate([
            'role' => [
                'required',
                'string',
                'exists:roles,name',
            ],
        ]);

        try {
            $this->staffManagementService->removeRole(
                actor: auth('staff')->user(),
                target: $staff,
                roleName: $validated['role']
            );

            $staff->refresh();

            return response()->json([
                'message' => 'Role removed successfully.',
                'roles' => $staff->getRoleNames(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function assignPermission(
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

        try {
            $this->staffManagementService->assignPermission(
                actor: auth('staff')->user(),
                target: $staff,
                permissionName: $validated['permission']
            );

            $staff->refresh();

            return response()->json([
                'message' => 'Permission assigned successfully.',
                'permissions' => $staff->getPermissionNames(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function revokePermission(
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

        try {
            $this->staffManagementService->revokePermission(
                actor: auth('staff')->user(),
                target: $staff,
                permissionName: $validated['permission']
            );

            $staff->refresh();

            return response()->json([
                'message' => 'Permission revoked successfully.',
                'permissions' => $staff->getPermissionNames(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }
    }
}
