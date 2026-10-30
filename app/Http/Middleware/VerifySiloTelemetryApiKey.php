<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySiloTelemetryApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow if authenticated via Sanctum
        if ($request->user()) {
            return $next($request);
        }

        // Otherwise check X-Telemetry-Key header
        $providedKey = $request->header('X-Telemetry-Key');
        $expectedKey = config('services.silo.telemetry_key');

        if (! $providedKey || ! hash_equals((string) $expectedKey, (string) $providedKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid or missing telemetry API key.',
            ], 401);
        }

        return $next($request);
    }
}
