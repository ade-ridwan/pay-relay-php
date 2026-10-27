<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Http\Controllers;

use AdeRidwan\PayRelayPhp\Models\PayRelayPayment;
use Illuminate\Routing\Controller;

class PaymentStatusController extends Controller
{
    public function show(string $referenceId)
    {
        $model = config('pay-relay.model', PayRelayPayment::class);
        $payment = $model::where('reference_id', $referenceId)->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }

        return response()->json([
            'reference_id' => $payment->reference_id,
            'status' => $payment->status->value,
            'amount_original' => $payment->amount_original,
            'amount_final' => $payment->amount_final,
            'unique_code' => $payment->unique_code,
            'qris_dynamic' => $payment->qris_dynamic,
            'expires_at' => $payment->expires_at->toIso8601String(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'created_at' => $payment->created_at->toIso8601String(),
        ]);
    }
}
