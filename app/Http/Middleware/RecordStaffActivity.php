<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordStaffActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $staffBefore = auth('staff')->user();
        $response = $next($request);

        try {
            $method = strtoupper($request->method());
            $path = '/' . ltrim($request->path(), '/');
            $statusCode = $response->getStatusCode();

            $isLogin = $path === '/api/auth/staff/login' && $method === 'POST' && $statusCode < 400;
            $isLogout = $path === '/api/auth/staff/logout' && $method === 'POST';
            $isMutating = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
                && !str_starts_with($path, '/api/auth/customer')
                && $path !== '/api/auth/staff/refresh';

            if (!$isLogin && !$isLogout && !$isMutating) {
                return $response;
            }

            $staff = auth('staff')->user() ?? $staffBefore;
            if (!$staff && $isLogin) {
                $email = (string) $request->input('email', '');
                $staff = Staff::where('email', $email)->first();
            }

            if (!$staff) {
                return $response;
            }

            [$action, $category, $description] = $this->describe($method, $path, $request);

            ActivityLog::create([
                'actor_id' => $staff->id,
                'actor_name' => $staff->name,
                'actor_email' => $staff->email,
                'action' => $action,
                'category' => $category,
                'description' => $description,
                'method' => $method,
                'path' => $path,
                'status_code' => $statusCode,
                'ip_address' => $request->ip(),
            ]);
        } catch (Throwable) {
            // Never block API responses if audit logging fails
        }

        return $response;
    }

    private function describe(string $method, string $path, Request $request): array
    {
        if ($path === '/api/auth/staff/login') {
            return ['staff.login', 'authentication', 'Signed in to Commerce Admin'];
        }
        if ($path === '/api/auth/staff/logout') {
            return ['staff.logout', 'authentication', 'Signed out of Commerce Admin'];
        }
        if (str_contains($path, '/roles')) {
            $role = (string) $request->input('role', 'role');
            $verb = $method === 'DELETE' ? 'Removed role' : 'Assigned role';
            return ['staff.role', 'access', "{$verb} ({$role})"];
        }
        if (str_contains($path, '/permissions')) {
            $perm = (string) $request->input('permission', 'permission');
            $verb = $method === 'DELETE' ? 'Revoked permission' : 'Granted permission';
            return ['staff.permission', 'access', "{$verb} ({$perm})"];
        }
        if (str_starts_with($path, '/api/staff')) {
            if ($method === 'POST') {
                return ['staff.created', 'team', 'Created staff account ' . (string) $request->input('email', '')];
            }
            if ($method === 'DELETE') {
                return ['staff.deleted', 'team', 'Removed staff account'];
            }
            return ['staff.updated', 'team', 'Updated staff profile'];
        }

        return [strtolower($method) . '.resource', 'workspace', "{$method} {$path}"];
    }
}
