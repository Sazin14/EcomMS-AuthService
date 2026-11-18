<?php

namespace App\Services;

use App\Models\Staff;
use Illuminate\Support\Facades\Redis;

class AuthorizationCacheService
{
    private const PREFIX = 'authz:staff:';

    private function key(int $staffId): string
    {
        return self::PREFIX . $staffId;
    }

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

    public function put(Staff $staff): array
    {
        $data = $this->build($staff);

        Redis::connection('authz')->set(
            $this->key($staff->id),
            json_encode($data)
        );

        return $data;
    }

    public function get(int $staffId): ?array
    {
        $data = Redis::connection('authz')->get($this->key($staffId));

        if ($data === null) {
            return null;
        }

        return json_decode($data, true);
    }

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

    public function forget(int $staffId): void
    {
        Redis::connection('authz')->del($this->key($staffId));
    }

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
