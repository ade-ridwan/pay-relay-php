<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('pay-relay.webhook.verify_signature', true)) {
            return $next($request);
        }

        $secret = config('pay-relay.webhook_secret');

        if (empty($secret)) {
            return response()->json(['message' => 'Webhook secret not configured.'], 500);
        }

        $headerName = config('pay-relay.webhook.signature_header', 'X-Pay-Relay-Signature');

        $signature = $request->header($headerName)
            ?? $request->header(strtoupper($headerName));

        if (empty($signature)) {
            return response()->json(['message' => 'Missing webhook signature.'], 401);
        }

        $payload = $request->getContent();
        $expected = strpos($signature, 'sha256=') === 0 ? 'sha256=' . hash_hmac('sha256', $payload, $secret)
            : hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        return $next($request);
    }
}
