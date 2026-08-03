<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSecret
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.inbound_email.secret');

        if ($secret && $request->header('X-Webhook-Token') !== $secret) {
            return response()->json([
                'error' => 'Unauthorized. Invalid webhook token.',
            ], 401);
        }

        return $next($request);
    }
}
