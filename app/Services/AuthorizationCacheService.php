<?php

namespace App\Services;

use App\Models\Staff;
use Illuminate\Support\Facades\Redis;

class AuthorizationCacheService
{
    private const PREFIX = 'authz:staff:';

    /**
     * Get Redis key for a staff member.
     */
    private function key(int $staffId): string
    {
        return self::PREFIX . $staffId;
    }

    /**
     * Build authorization data from the database.
     */
    public function build(Staff $staff): array
    {
        $staff->loadMissing('roles.permissions');

        return [
            'user_id' => $staff->id,
            'type' => 'staff',

            'roles' => $staff->roles
                ->pluck('name')
                ->values()
                ->toArray(),

            'permissions' => $staff->getAllPermissions()
                ->pluck('name')
                ->values()
                ->toArray(),

            'version' => now()->timestamp,
        ];
    }

    /**
     * Store/replace authorization data in Redis.
     */
    public function put(Staff $staff): array
    {
        $data = $this->build($staff);

        Redis::set(
            $this->key($staff->id),
            json_encode($data)
        );

        return $data;
    }

    /**
     * Retrieve authorization data from Redis.
     */
    public function get(int $staffId): ?array
    {
        $data = Redis::get($this->key($staffId));

        if ($data === null) {
            return null;
        }

        return json_decode($data, true);
    }

    /**
     * Check whether a permission exists in cached authorization data.
     */
    public function hasPermission(
        int $staffId,
        string $permission
    ): bool {
        $data = $this->get($staffId);

        if (!$data) {
            return false;
        }

        return in_array(
            $permission,
            $data['permissions'] ?? [],
            true
        );
    }

    /**
     * Check whether a staff member has a role.
     */
    public function hasRole(
        int $staffId,
        string $role
    ): bool {
        $data = $this->get($staffId);

        if (!$data) {
            return false;
        }

        return in_array(
            $role,
            $data['roles'] ?? [],
            true
        );
    }

    /**
     * Remove authorization cache.
     */
    public function forget(int $staffId): void
    {
        Redis::del($this->key($staffId));
    }

    /**
     * Rebuild authorization cache directly from DB.
     */
    public function refresh(int $staffId): ?array
    {
        $staff = Staff::find($staffId);

        if (!$staff) {
            $this->forget($staffId);

            return null;
        }

        return $this->put($staff);
    }
}