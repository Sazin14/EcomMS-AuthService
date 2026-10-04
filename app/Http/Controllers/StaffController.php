<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {
    }

    /**
     * List all staff members.
     */
    public function index(): JsonResponse
    {
        $staff = Staff::with('roles')
            ->latest()
            ->paginate(20);

        return response()->json($staff);
    }

    /**
     * Create a new staff member.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],

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

        $staff = $this->authService->createStaff($validated);

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

    /**
     * Show a specific staff member.
     */
    public function show(Staff $staff): JsonResponse
    {
        $staff->load('roles', 'permissions');

        return response()->json([
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'roles' => $staff->getRoleNames(),
                'permissions' => $staff->getPermissionNames(),
            ],
        ]);
    }

    /**
     * Update staff information.
     */
    public function update(
        Request $request,
        Staff $staff
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],

            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('staffs', 'email')
                    ->ignore($staff->id),
            ],
        ]);

        $staff->update($validated);

        return response()->json([
            'message' => 'Staff updated successfully.',
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
            ],
        ]);
    }

    /**
     * Delete a staff member.
     */
    public function destroy(Staff $staff): JsonResponse
    {
        $staff->delete();

        return response()->json([
            'message' => 'Staff deleted successfully.',
        ]);
    }

    /**
     * Assign a role to a staff member.
     */
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

        $staff->assignRole($validated['role']);

        return response()->json([
            'message' => 'Role assigned successfully.',
            'roles' => $staff->getRoleNames(),
        ]);
    }

    /**
     * Remove a role from a staff member.
     */
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

        $staff->removeRole($validated['role']);

        return response()->json([
            'message' => 'Role removed successfully.',
            'roles' => $staff->getRoleNames(),
        ]);
    }
}