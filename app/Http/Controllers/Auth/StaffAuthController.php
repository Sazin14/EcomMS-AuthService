<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\StaffLoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class StaffAuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {
    }

    public function login(StaffLoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->loginStaff(
                $request->validated()
            );

            return response()->json($result);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->refreshStaff(
                $request->validated('refresh_token')
            );

            return response()->json($result);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function logout(RefreshTokenRequest $request): JsonResponse
    {
        $this->authService->logoutStaff(
            $request->validated('refresh_token')
        );

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(): JsonResponse
    {
        /** @var Staff $staff */
        $staff = auth('staff')->user();

        return response()->json([
            'user' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'type' => 'staff',
                'roles' => $staff->getRoleNames(),
                'permissions' => $staff->getAllPermissions()
                    ->pluck('name')
                    ->values(),
            ],
        ]);
    }
}