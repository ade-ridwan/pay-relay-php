<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Http\Controllers;

use AdeRidwan\PayRelayPhp\Enums\PaymentStatus;
use AdeRidwan\PayRelayPhp\Events\PaymentPaid;
use AdeRidwan\PayRelayPhp\Models\PayRelayPayment;
use AdeRidwan\PayRelayPhp\Support\UniqueCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $amount = UniqueCodeGenerator::normalizeAmount($request->input('amount'));

        // Fallback: try amount_text if amount not present
        if ($amount === null) {
            $amount = UniqueCodeGenerator::normalizeAmount($request->input('amount_text'));
        }

        if ($amount === null || $amount <= 0) {
            Log::warning('pay-relay: webhook invalid amount', ['payload' => $request->all()]);

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
                'payload' => $request->all(),
            ]);

            return response()->json(['matched' => false, 'message' => 'No pending payment found for amount.'], 404);
        }

        $payment->update([
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'raw_notification' => $request->all(),
        ]);

        event(new PaymentPaid($payment));

        return response()->json([
            'matched' => true,
            'reference_id' => $payment->reference_id,
            'amount_final' => $payment->amount_final,
        ]);
    }
}
