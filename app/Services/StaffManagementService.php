<?php

namespace App\Services;

use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StaffManagementService
{
    private const ROLE_LEVELS = [
        'super-admin' => 100,
        'admin' => 90,
        'general-manager' => 50,
        'product-manager' => 40,
        'inventory-manager' => 40,
        'sales-manager' => 40,
    ];

    public function __construct(
        private AuthorizationCacheService $authorizationCacheService
    ) {
    }

    public function create(
        Staff $actor,
        array $data
    ): Staff {
        if (! $actor->can('staff.create')) {
            throw new RuntimeException(
                'You do not have permission [staff.create].'
            );
        }

        $roleName = $data['role'] ?? null;

        if (! $roleName) {
            throw new RuntimeException(
                'A role is required when creating staff.'
            );
        }

        $role = Role::where('name', $roleName)
            ->where('guard_name', 'staff')
            ->first();

        if (! $role) {
            throw new RuntimeException(
                "Role [{$roleName}] does not exist."
            );
        }

        $this->ensureCanCreateRole(
            actor: $actor,
            roleName: $roleName
        );

        $staff = DB::transaction(function () use ($data, $role) {
            $staff = Staff::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $staff->assignRole($role);

            return $staff;
        });

        $this->authorizationCacheService->put($staff);

        return $staff;
    }

    public function updateProfile(
        Staff $actor,
        Staff $target,
        array $data
    ): void {
        if ($actor->id !== $target->id) {
            $this->authorizeStaffAction(
                actor: $actor,
                target: $target,
                permission: 'staff.update'
            );
        }

        $target->update(array_intersect_key($data, array_flip(['name', 'email'])));
    }

    public function delete(
        Staff $actor,
        Staff $target
    ): void {
        $this->authorizeStaffAction(
            actor: $actor,
            target: $target,
            permission: 'staff.delete'
        );

        $targetId = $target->id;

        DB::transaction(function () use ($target) {
            $target->delete();
        });

        $this->authorizationCacheService->forget($targetId);
    }

    public function assignRole(
        Staff $actor,
        Staff $target,
        string $roleName
    ): void {
        $this->authorizeStaffAction(
            actor: $actor,
            target: $target,
            permission: 'staff.assign-role'
        );

        $role = Role::where('name', $roleName)
            ->where('guard_name', 'staff')
            ->first();

        if (! $role) {
            throw new RuntimeException(
                "Role [{$roleName}] does not exist."
            );
        }

        $this->ensureCanAssignRole(
            actor: $actor,
            roleName: $roleName
        );

        $target->assignRole($role);

        $this->authorizationCacheService->refresh($target->id);
    }

    public function removeRole(
        Staff $actor,
        Staff $target,
        string $roleName
    ): void {
        $this->authorizeStaffAction(
            actor: $actor,
            target: $target,
            permission: 'staff.remove-role'
        );

        if (! $target->hasRole($roleName)) {
            throw new RuntimeException(
                "Staff member does not have the [{$roleName}] role."
            );
        }

        $this->ensureCanModifyRole(
            actor: $actor,
            target: $target,
            roleName: $roleName
        );

        if ($target->roles()->count() <= 1) {
            throw new RuntimeException(
                'Staff member must have at least one role.'
            );
        }

        $target->removeRole($roleName);

        $this->authorizationCacheService->refresh($target->id);
    }

    public function assignPermission(
        Staff $actor,
        Staff $target,
        string $permissionName
    ): void {
        $this->authorizeStaffAction(
            actor: $actor,
            target: $target,
            permission: 'permission.assign'
        );

        $permission = Permission::where('name', $permissionName)
            ->where('guard_name', 'staff')
            ->first();

        if (! $permission) {
            throw new RuntimeException(
                "Permission [{$permissionName}] does not exist."
            );
        }

        $this->ensureCanAssignPermission(
            actor: $actor,
            permissionName: $permissionName
        );

        $target->givePermissionTo($permission);

        $this->authorizationCacheService->refresh($target->id);
    }

    public function revokePermission(
        Staff $actor,
        Staff $target,
        string $permissionName
    ): void {
        $this->authorizeStaffAction(
            actor: $actor,
            target: $target,
            permission: 'permission.revoke'
        );

        if (! $target->hasDirectPermission($permissionName)) {
            throw new RuntimeException(
                "Staff member does not have the direct permission [{$permissionName}]."
            );
        }

        $this->ensureCanModifyPermission(
            actor: $actor,
            target: $target,
            permissionName: $permissionName
        );

        $target->revokePermissionTo($permissionName);

        $this->authorizationCacheService->refresh($target->id);
    }

    private function authorizeStaffAction(
        Staff $actor,
        Staff $target,
        string $permission
    ): void {
        if ($actor->id === $target->id) {
            throw new RuntimeException(
                'You cannot perform this staff management action on yourself.'
            );
        }

        if (! $actor->can($permission)) {
            throw new RuntimeException(
                "You do not have permission [{$permission}]."
            );
        }

        if (! $this->canManageTarget($actor, $target)) {
            throw new RuntimeException(
                'You are not allowed to manage this staff member.'
            );
        }
    }

    private function canManageTarget(
        Staff $actor,
        Staff $target
    ): bool {
        if ($actor->hasRole('super-admin')) {
            return true;
        }

        $actorLevel = $this->highestRoleLevel($actor);
        $targetLevel = $this->highestRoleLevel($target);

        return $actorLevel > $targetLevel;
    }

    private function ensureCanAssignRole(
        Staff $actor,
        string $roleName
    ): void {
        $roleLevel = $this->roleLevel($roleName);

        if (
            $roleName === 'super-admin' &&
            ! $actor->hasRole('super-admin')
        ) {
            throw new RuntimeException(
                'Only a Super Admin can assign the Super Admin role.'
            );
        }

        if (
            $roleName === 'admin' &&
            ! $actor->hasRole('super-admin')
        ) {
            throw new RuntimeException(
                'Only a Super Admin can assign the Admin role.'
            );
        }

        if (
            ! $actor->hasRole('super-admin') &&
            $roleLevel >= $this->highestRoleLevel($actor)
        ) {
            throw new RuntimeException(
                'You cannot assign a role equal to or higher than your own role.'
            );
        }
    }

    private function ensureCanModifyRole(
        Staff $actor,
        Staff $target,
        string $roleName
    ): void {
        if ($actor->hasRole('super-admin')) {
            return;
        }

        if (
            $roleName === 'super-admin' ||
            $roleName === 'admin'
        ) {
            throw new RuntimeException(
                'You cannot remove this role.'
            );
        }

        if (! $this->canManageTarget($actor, $target)) {
            throw new RuntimeException(
                'You cannot modify this staff member.'
            );
        }
    }

    private function ensureCanAssignPermission(
        Staff $actor,
        string $permissionName
    ): void {
        if ($actor->hasRole('super-admin')) {
            return;
        }

        $protectedPermissions = [
            'role.create',
            'role.update',
            'role.delete',
            'permission.assign',
            'permission.revoke',
        ];

        if (
            in_array(
                $permissionName,
                $protectedPermissions,
                true
            )
        ) {
            throw new RuntimeException(
                'You are not allowed to grant this permission.'
            );
        }
    }

    private function ensureCanModifyPermission(
        Staff $actor,
        Staff $target,
        string $permissionName
    ): void {
        if ($actor->hasRole('super-admin')) {
            return;
        }

        if (! $this->canManageTarget($actor, $target)) {
            throw new RuntimeException(
                'You cannot modify permissions for this staff member.'
            );
        }

        $protectedPermissions = [
            'role.create',
            'role.update',
            'role.delete',
            'permission.assign',
            'permission.revoke',
        ];

        if (
            in_array(
                $permissionName,
                $protectedPermissions,
                true
            )
        ) {
            throw new RuntimeException(
                'You are not allowed to modify this permission.'
            );
        }
    }

    private function highestRoleLevel(Staff $staff): int
    {
        $levels = $staff->getRoleNames()
            ->map(
                fn (string $role) => $this->roleLevel($role)
            );

        return $levels->max() ?? 0;
    }

    private function roleLevel(string $roleName): int
    {
        return self::ROLE_LEVELS[$roleName] ?? 0;
    }

    private function ensureCanCreateRole(
        Staff $actor,
        string $roleName
    ): void {
        if ($actor->hasRole('super-admin')) {
            return;
        }

        if ($roleName === 'admin') {
            throw new RuntimeException(
                'Only a Super Admin can create an Admin staff member.'
            );
        }

        if ($roleName === 'super-admin') {
            throw new RuntimeException(
                'Only a Super Admin can create a Super Admin staff member.'
            );
        }

        $actorLevel = $this->highestRoleLevel($actor);
        $targetLevel = $this->roleLevel($roleName);

        if ($targetLevel >= $actorLevel) {
            throw new RuntimeException(
                'You cannot create a staff member with a role equal to or higher than your own.'
            );
        }
    }
}
