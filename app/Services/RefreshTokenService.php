<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use RuntimeException;

class RefreshTokenService
{
    private const CLAIM = <<<'LUA'
local raw = redis.call('GET', KEYS[1])
if not raw then return nil end
local data = cjson.decode(raw)
if data['revoked'] then return {'reused', raw} end
data['revoked'] = true
local ttl = redis.call('TTL', KEYS[1])
if ttl > 0 then redis.call('SETEX', KEYS[1], ttl, cjson.encode(data)) end
return {'ok', raw}
LUA;

    public function create(
        int $userId,
        string $userType,
        ?string $familyId = null
    ): string {
        $token = bin2hex(random_bytes(64));
        $tokenHash = hash('sha256', $token);
        $familyId ??= (string) Str::uuid();

        $ttl = (int) config('jwt.refresh_ttl') * 60;

        $data = [
            'user_id' => $userId,
            'user_type' => $userType,
            'family_id' => $familyId,
            'expires_at' => now()->addSeconds($ttl)->timestamp,
            'revoked' => false,
        ];

        Redis::setex($this->key($tokenHash), $ttl, json_encode($data));

        Redis::sadd($this->familyKey($familyId), $tokenHash);
        Redis::expire($this->familyKey($familyId), $ttl);

        return $token;
    }

    public function peek(string $token): array
    {
        $raw = Redis::get($this->key(hash('sha256', $token)));

        if ($raw === null || $raw === false) {
            throw new RuntimeException('Invalid or expired refresh token.');
        }

        return (array) json_decode($raw, true);
    }

    public function validate(string $token): array
    {
        $tokenHash = hash('sha256', $token);
        $raw = Redis::get($this->key($tokenHash));

        if ($raw === null || $raw === false) {
            throw new RuntimeException('Invalid or expired refresh token.');
        }

        $data = json_decode($raw, true);

        if ($data['revoked'] ?? false) {
            throw new RuntimeException('Refresh token has been revoked.');
        }

        if (($data['expires_at'] ?? 0) < now()->timestamp) {
            throw new RuntimeException('Refresh token has expired.');
        }

        return ['token_hash' => $tokenHash, ...$data];
    }

    public function revoke(string $token): void
    {
        Redis::eval(self::CLAIM, 1, $this->key(hash('sha256', $token)));
    }

    public function revokeFamily(string $familyId): void
    {
        $hashes = Redis::smembers($this->familyKey($familyId)) ?: [];

        foreach ($hashes as $hash) {
            Redis::eval(self::CLAIM, 1, $this->key((string) $hash));
        }
    }

    /** @return array{0: array{user_id:int, user_type:string, family_id:string, expires_at:int}, 1: string} */
    public function rotate(string $token): array
    {
        $result = Redis::eval(self::CLAIM, 1, $this->key(hash('sha256', $token)));

        if (! is_array($result) || count($result) !== 2) {
            throw new RuntimeException('Invalid or expired refresh token.');
        }

        [$state, $raw] = $result;
        $data = json_decode((string) $raw, true);

        if ($state === 'reused') {
            if (! empty($data['family_id'])) {
                $this->revokeFamily($data['family_id']);
            }

            throw new RuntimeException('Refresh token reuse detected. Please log in again.');
        }

        if (($data['expires_at'] ?? 0) < now()->timestamp) {
            throw new RuntimeException('Refresh token has expired.');
        }

        $newToken = $this->create(
            userId: (int) $data['user_id'],
            userType: (string) $data['user_type'],
            familyId: (string) $data['family_id'],
        );

        return [$data, $newToken];
    }

    private function key(string $tokenHash): string
    {
        return "auth:refresh:{$tokenHash}";
    }

    private function familyKey(string $familyId): string
    {
        return "auth:refresh-family:{$familyId}";
    }
}
