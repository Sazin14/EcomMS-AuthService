<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerLoginRequest;
use App\Http\Requests\Auth\CustomerRegisterRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class CustomerAuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {
    }

    public function register(
        CustomerRegisterRequest $request
    ): JsonResponse {
        $customer = $this->authService->registerCustomer(
            $request->validated()
        );

        return response()->json([
            'message' => 'Customer registered successfully.',
            'user' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'type' => 'customer',
            ],
        ], 201);
    }

    public function login(CustomerLoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->loginCustomer(
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
            $result = $this->authService->refreshCustomer(
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
        $this->authService->logoutCustomer(
            $request->validated('refresh_token')
        );

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}