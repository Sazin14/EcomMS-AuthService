<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

class AuthService
{
    public function __construct(
        private RefreshTokenService $refreshTokenService,
        private AuthorizationCacheService $authorizationCacheService,
    ) {
    }

    public function loginStaff(array $credentials): array
    {
        return $this->login(
            guard: 'staff',
            credentials: $credentials,
            userType: 'staff'
        );
    }

    public function refreshStaff(string $refreshToken): array
    {
        return $this->refresh(
            guard: 'staff',
            refreshToken: $refreshToken,
            userType: 'staff'
        );
    }

    public function logoutStaff(string $refreshToken, ?string $accessToken = null): void
    {
        $this->refreshTokenService->revoke($refreshToken);
        $this->revokeAccessToken('staff', $accessToken);
    }

    public function registerCustomer(array $data): Customer
    {
        return Customer::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }

    public function loginCustomer(array $credentials): array
    {
        return $this->login(
            guard: 'customer',
            credentials: $credentials,
            userType: 'customer'
        );
    }

    public function refreshCustomer(string $refreshToken): array
    {
        return $this->refresh(
            guard: 'customer',
            refreshToken: $refreshToken,
            userType: 'customer'
        );
    }

    public function logoutCustomer(string $refreshToken, ?string $accessToken = null): void
    {
        $this->refreshTokenService->revoke($refreshToken);
        $this->revokeAccessToken('customer', $accessToken);
    }

    private function revokeAccessToken(string $guard, ?string $accessToken): void
    {
        if (! $accessToken) {
            return;
        }

        try {
            $payload = auth($guard)->setToken($accessToken)->getPayload();
        } catch (\Throwable) {
            return;
        }

        $ttl = (int) $payload->get('exp') - time();

        if ($ttl > 0) {
            Redis::connection('authz')->setex('revoked-jti:'.$payload->get('jti'), $ttl, '1');
        }

        try {
            auth($guard)->invalidate();
        } catch (\Throwable) {
        }
    }

    private function login(
        string $guard,
        array $credentials,
        string $userType
    ): array {
        $token = auth($guard)->attempt($credentials);

        if (! $token) {
            throw new RuntimeException('Invalid credentials.');
        }

        $user = auth($guard)->user();

        if ($userType === 'staff') {
            $this->authorizationCacheService->put($user);
        }

        $refreshToken = $this->refreshTokenService->create(
            userId: $user->getKey(),
            userType: $userType,
        );

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'refresh_token' => $refreshToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $userType,
            ],
        ];
    }

    private function refresh(
        string $guard,
        string $refreshToken,
        string $userType
    ): array {
        $peek = $this->refreshTokenService->peek($refreshToken);

        if (($peek['user_type'] ?? null) !== $userType) {
            throw new RuntimeException('Invalid refresh token.');
        }

        [$data, $newRefreshToken] = $this->refreshTokenService->rotate(
            $refreshToken
        );

        $user = $this->findUser(
            userType: $userType,
            userId: $data['user_id']
        );

        if (! $user) {
            throw new RuntimeException('User not found.');
        }

        $accessToken = auth($guard)->login($user);

        if ($userType === 'staff') {
            $this->authorizationCacheService->put($user);
        }

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'refresh_token' => $newRefreshToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $userType,
            ],
        ];
    }

    private function findUser(
        string $userType,
        int $userId
    ): ?Model {
        return match ($userType) {
            'staff' => Staff::find($userId),
            'customer' => Customer::find($userId),
            default => null,
        };
    }
}
