<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use RuntimeException;

class RefreshTokenService
{
    
    public function create(
        int $userId,
        string $userType,
        ?string $familyId = null
    ): string {
        $token = bin2hex(random_bytes(64));

        $tokenHash = hash('sha256', $token);

        $familyId ??= (string) Str::uuid();

        $expiresAt = now()->addMinutes(
            config('jwt.refresh_ttl')
        );

        $data = [
            'user_id' => $userId,
            'user_type' => $userType,
            'family_id' => $familyId,
            'expires_at' => $expiresAt->timestamp,
            'revoked' => false,
        ];

        $ttl = now()->diffInSeconds($expiresAt);

        Redis::setex(
            $this->key($tokenHash),
            (int) $ttl,
            json_encode($data)
        );

        return $token;
    }

    

    public function validate(string $token): array
    {
        $tokenHash = hash('sha256', $token);

        $data = Redis::get($this->key($tokenHash));

        if ($data === null) {
            throw new RuntimeException('Invalid or expired refresh token.');
        }

        $data = json_decode($data, true);

        if ($data['revoked'] ?? false) {
            throw new RuntimeException('Refresh token has been revoked.');
        }

        if (($data['expires_at'] ?? 0) < now()->timestamp) {
            throw new RuntimeException('Refresh token has expired.');
        }

        return [
            'token_hash' => $tokenHash,
            ...$data,
        ];
    }

    
    public function revoke(string $token): void
    {
        $tokenHash = hash('sha256', $token);

        $key = $this->key($tokenHash);

        $data = Redis::get($key);

        if ($data === null) {
            return;
        }

        $data = json_decode($data, true);

        $data['revoked'] = true;

        $ttl = Redis::ttl($key);

        if ($ttl > 0) {
            Redis::setex(
                $key,
                $ttl,
                json_encode($data)
            );
        }
    }



    public function revokeFamily(string $familyId): void
    {
        $keys = Redis::keys('auth:refresh:*');

        if (!is_array($keys)) {
            return;
        }

        foreach ($keys as $key) {
            $data = Redis::get($key);

            if ($data === null) {
                continue;
            }

            $data = json_decode($data, true);

            if (($data['family_id'] ?? null) !== $familyId) {
                continue;
            }

            $data['revoked'] = true;

            $ttl = Redis::ttl($key);

            if ($ttl > 0) {
                Redis::setex(
                    $key,
                    $ttl,
                    json_encode($data)
                );
            }
        }
    }

    

    public function rotate(string $token): string
    {
        $tokenHash = hash('sha256', $token);

        $key = $this->key($tokenHash);

        $rawData = Redis::get($key);

        if ($rawData === null) {
            throw new RuntimeException(
                'Invalid or expired refresh token.'
            );
        }

        $data = json_decode($rawData, true);

        /*
        * A revoked refresh token is being used again.
        * This indicates possible token theft/reuse.
        */
        if ($data['revoked'] ?? false) {
            $this->revokeFamily($data['family_id']);

            throw new RuntimeException(
                'Refresh token reuse detected. Token family revoked.'
            );
        }

        if (($data['expires_at'] ?? 0) < now()->timestamp) {
            throw new RuntimeException(
                'Refresh token has expired.'
            );
        }

        // Revoke the current refresh token.
        $this->revoke($token);

        // Create replacement token in the same family.
        return $this->create(
            userId: $data['user_id'],
            userType: $data['user_type'],
            familyId: $data['family_id'],
        );
    }

    

    private function key(string $tokenHash): string
    {
        return "auth:refresh:{$tokenHash}";
    }
}