<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MesAuthService
{
    public const CACHE_KEY = 'mes_api_jwt_token';

    /**
     * Retrieve a cached JWT access token or authenticate with MES to obtain a new one.
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        if (! $forceRefresh && Cache::has(self::CACHE_KEY)) {
            $cachedToken = Cache::get(self::CACHE_KEY);
            if (is_string($cachedToken) && ! empty($cachedToken)) {
                return $cachedToken;
            }
        }

        return $this->authenticate();
    }

    /**
     * Perform login handshake against MES POST /api/v1/auth/login.
     */
    public function authenticate(): string
    {
        $baseUrl = rtrim((string) config('services.mes.base_url', 'http://127.0.0.1:3000'), '/');
        $username = (string) config('services.mes.username', 'admin');
        $password = (string) config('services.mes.password', 'password123');

        $loginUrl = "{$baseUrl}/api/v1/auth/login";

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->post($loginUrl, [
                    'username' => $username,
                    'password' => $password,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException("MES authentication failed with HTTP status {$response->status()}: ".$response->body());
            }

            $token = (string) ($response->json('accessToken') ?? $response->json('data.accessToken'));
            if (empty($token)) {
                throw new RuntimeException('MES login response did not contain an accessToken.');
            }

            // Cache token for 7 hours (MES default expiration is 8h)
            Cache::put(self::CACHE_KEY, $token, now()->addHours(7));
            Log::info('Successfully acquired and cached MES JWT access token.');

            return $token;
        } catch (\Throwable $e) {
            Log::error("MesAuthService handshake failed for [{$loginUrl}]: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Invalidate cached token (e.g. upon receiving 401 Unauthorized during dispatch).
     */
    public function forgetToken(): void
    {
        Cache::forget(self::CACHE_KEY);
        Log::info('Cleared cached MES JWT token.');
    }
}
