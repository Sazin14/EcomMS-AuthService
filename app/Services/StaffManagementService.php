<?php

namespace App\Services;

use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StaffManagementService
{
    /**
     * Higher number = higher authority.
     */
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

        // Create the Redis authorization record immediately.
        $this->authorizationCacheService->put($staff);

        return $staff;
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Staff
    |--------------------------------------------------------------------------
    */

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

        // Remove deleted staff's authorization cache.
        $this->authorizationCacheService->forget($targetId);
    }

    /*
    |--------------------------------------------------------------------------
    | Assign Role
    |--------------------------------------------------------------------------
    */

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

        // Role changed -> rebuild authorization cache.
        $this->authorizationCacheService->refresh($target->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Remove Role
    |--------------------------------------------------------------------------
    */

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

        /*
         * Prevent a staff member from becoming completely
         * role-less unless that is explicitly intended.
         */
        if ($target->roles()->count() <= 1) {
            throw new RuntimeException(
                'Staff member must have at least one role.'
            );
        }

        $target->removeRole($roleName);

        // Role changed -> rebuild authorization cache.
        $this->authorizationCacheService->refresh($target->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Assign Permission
    |--------------------------------------------------------------------------
    */

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

        // Permission changed -> rebuild authorization cache.
        $this->authorizationCacheService->refresh($target->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Revoke Permission
    |--------------------------------------------------------------------------
    */

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

        // Permission changed -> rebuild authorization cache.
        $this->authorizationCacheService->refresh($target->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Generic Staff Authorization
    |--------------------------------------------------------------------------
    */

    private function authorizeStaffAction(
        Staff $actor,
        Staff $target,
        string $permission
    ): void {
        /*
         * Prevent staff from managing themselves through
         * administrative staff-management endpoints.
         */
        if ($actor->id === $target->id) {
            throw new RuntimeException(
                'You cannot perform this staff management action on yourself.'
            );
        }

        /*
         * Spatie answers:
         *
         * "Does this actor have the generic permission?"
         */
        if (! $actor->can($permission)) {
            throw new RuntimeException(
                "You do not have permission [{$permission}]."
            );
        }

        /*
         * Our hierarchy answers:
         *
         * "Can this actor perform this operation on this target?"
         */
        if (! $this->canManageTarget($actor, $target)) {
            throw new RuntimeException(
                'You are not allowed to manage this staff member.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Target Hierarchy
    |--------------------------------------------------------------------------
    */

    private function canManageTarget(
        Staff $actor,
        Staff $target
    ): bool {
        /*
         * Super Admin can manage everyone.
         */
        if ($actor->hasRole('super-admin')) {
            return true;
        }

        /*
         * Find the highest role of the actor and target.
         */
        $actorLevel = $this->highestRoleLevel($actor);
        $targetLevel = $this->highestRoleLevel($target);

        /*
         * A staff member can only manage someone below
         * their own authority level.
         */
        return $actorLevel > $targetLevel;
    }

    /*
    |--------------------------------------------------------------------------
    | Role Assignment Rules
    |--------------------------------------------------------------------------
    */

    private function ensureCanAssignRole(
        Staff $actor,
        string $roleName
    ): void {
        $roleLevel = $this->roleLevel($roleName);

        /*
         * Only Super Admin can assign Super Admin.
         */
        if (
            $roleName === 'super-admin' &&
            ! $actor->hasRole('super-admin')
        ) {
            throw new RuntimeException(
                'Only a Super Admin can assign the Super Admin role.'
            );
        }

        /*
         * Only Super Admin can assign Admin.
         */
        if (
            $roleName === 'admin' &&
            ! $actor->hasRole('super-admin')
        ) {
            throw new RuntimeException(
                'Only a Super Admin can assign the Admin role.'
            );
        }

        /*
         * Nobody can assign a role equal to or higher than
         * their own authority level.
         */
        if (
            ! $actor->hasRole('super-admin') &&
            $roleLevel >= $this->highestRoleLevel($actor)
        ) {
            throw new RuntimeException(
                'You cannot assign a role equal to or higher than your own role.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Role Removal Rules
    |--------------------------------------------------------------------------
    */

    private function ensureCanModifyRole(
        Staff $actor,
        Staff $target,
        string $roleName
    ): void {
        /*
         * Super Admin can modify any role.
         */
        if ($actor->hasRole('super-admin')) {
            return;
        }

        /*
         * Admin cannot remove Admin or Super Admin.
         */
        if (
            $roleName === 'super-admin' ||
            $roleName === 'admin'
        ) {
            throw new RuntimeException(
                'You cannot remove this role.'
            );
        }

        /*
         * The target itself must be below the actor.
         */
        if (! $this->canManageTarget($actor, $target)) {
            throw new RuntimeException(
                'You cannot modify this staff member.'
            );
        }

        /*
         * A lower-level role is removable.
         */
    }

    /*
    |--------------------------------------------------------------------------
    | Permission Assignment Rules
    |--------------------------------------------------------------------------
    */

    private function ensureCanAssignPermission(
        Staff $actor,
        string $permissionName
    ): void {
        /*
         * Super Admin can grant any permission.
         */
        if ($actor->hasRole('super-admin')) {
            return;
        }

        /*
         * Admin cannot grant permissions that could effectively
         * create another Admin/Super Admin.
         *
         * For now we protect the staff/role management permissions.
         */
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

    /*
    |--------------------------------------------------------------------------
    | Permission Modification Rules
    |--------------------------------------------------------------------------
    */

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

        /*
         * Admin-level authorization permissions should not
         * be removed/modified by ordinary Admins.
         */
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

    /*
    |--------------------------------------------------------------------------
    | Role Level Helpers
    |--------------------------------------------------------------------------
    */

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
        /*
        * Super Admin can create anyone.
        */
        if ($actor->hasRole('super-admin')) {
            return;
        }

        /*
        * Only Super Admin can create another Admin.
        */
        if ($roleName === 'admin') {
            throw new RuntimeException(
                'Only a Super Admin can create an Admin staff member.'
            );
        }

        /*
        * Nobody except Super Admin can create another
        * Super Admin.
        */
        if ($roleName === 'super-admin') {
            throw new RuntimeException(
                'Only a Super Admin can create a Super Admin staff member.'
            );
        }

        $actorLevel = $this->highestRoleLevel($actor);
        $targetLevel = $this->roleLevel($roleName);

        /*
        * The creator must be higher in the hierarchy
        * than the role being created.
        */
        if ($targetLevel >= $actorLevel) {
            throw new RuntimeException(
                'You cannot create a staff member with a role equal to or higher than your own.'
            );
        }
    }
}


