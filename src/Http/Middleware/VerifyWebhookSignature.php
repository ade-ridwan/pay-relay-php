<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('pay-relay.webhook_secret');

        if (empty($secret)) {
            return response()->json(['message' => 'Webhook secret not configured.'], 500);
        }

        $signature = $request->header('X-Pay-Relay-Signature')
            ?? $request->header('X-PAY-RELAY-SIGNATURE');

        if (empty($signature)) {
            return response()->json(['message' => 'Missing webhook signature.'], 401);
        }

        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        return $next($request);
    }
}
