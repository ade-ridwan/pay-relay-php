<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Http\Controllers;

use AdeRidwan\PayRelayPhp\Contracts\WebhookParserInterface;
use AdeRidwan\PayRelayPhp\Enums\PaymentStatus;
use AdeRidwan\PayRelayPhp\Events\PaymentPaid;
use AdeRidwan\PayRelayPhp\Models\PayRelayPayment;
use AdeRidwan\PayRelayPhp\Support\DefaultWebhookParser;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $parserClass = config('pay-relay.webhook.parser', DefaultWebhookParser::class);

        if (!is_string($parserClass) || !class_exists($parserClass)) {
            Log::error('pay-relay: webhook parser class not found', ['parser' => $parserClass]);

            return response()->json(['message' => 'Webhook parser misconfigured.'], 500);
        }

        $parser = app($parserClass);

        if (!$parser instanceof WebhookParserInterface) {
            Log::error('pay-relay: webhook parser must implement WebhookParserInterface', ['parser' => $parserClass]);

            return response()->json(['message' => 'Webhook parser misconfigured.'], 500);
        }

        $amount = $parser->parseAmount($request);
        $payload = $parser->parsePayload($request);

        if ($amount === null || $amount <= 0) {
            Log::warning('pay-relay: webhook invalid amount', ['payload' => $payload]);

            return response()->json(['matched' => false, 'message' => 'Invalid amount.'], 422);
        }

        $model = config('pay-relay.model', PayRelayPayment::class);

        // Idempotency: already paid with same amount_final
        $alreadyPaid = $model::where('amount_final', $amount)
            ->where('status', PaymentStatus::Paid->value)
            ->latest('paid_at')
            ->first();

        if ($alreadyPaid) {
            return response()->json([
                'matched' => true,
                'already_paid' => true,
                'reference_id' => $alreadyPaid->reference_id,
            ]);
        }

        $payment = $model::where('amount_final', $amount)
            ->where('status', PaymentStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->oldest()
            ->first();

        if (!$payment) {
            Log::warning('pay-relay: webhook unmatched', [
                'amount' => $amount,
                'payload' => $payload,
            ]);

            return response()->json(['matched' => false, 'message' => 'No pending payment found for amount.'], 404);
        }

        $payment->update([
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'raw_notification' => $payload,
        ]);

        event(new PaymentPaid($payment));

        return response()->json([
            'matched' => true,
            'reference_id' => $payment->reference_id,
            'amount_final' => $payment->amount_final,
        ]);
    }
}
