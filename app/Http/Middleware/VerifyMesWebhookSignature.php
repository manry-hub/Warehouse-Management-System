<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMesWebhookSignature
{
    /**
     * Handle an incoming request and verify the HMAC SHA-256 signature from MES.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-MES-Signature');
        $secret = config('services.mes.webhook_secret', env('MES_WEBHOOK_SECRET', 'test-mes-secret-key'));

        if (! $signature) {
            return response()->json([
                'success' => false,
                'message' => 'Missing X-MES-Signature header.',
            ], 401);
        }

        $rawBody = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $rawBody, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid HMAC SHA-256 signature.',
            ], 401);
        }

        return $next($request);
    }
}
