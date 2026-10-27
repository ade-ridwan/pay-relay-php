<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp;

use AdeRidwan\PayRelayPhp\Enums\PaymentStatus;
use AdeRidwan\PayRelayPhp\Events\PaymentCreated;
use AdeRidwan\PayRelayPhp\Events\PaymentExpired;
use AdeRidwan\PayRelayPhp\Models\PayRelayPayment;
use AdeRidwan\PayRelayPhp\Qris\QrisRelay;
use AdeRidwan\PayRelayPhp\Support\UniqueCodeGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PayRelay
{
    public static function model(): string
    {
        return config('pay-relay.model', PayRelayPayment::class);
    }

    /**
     * Create a new payment with QRIS dynamic.
     * Pass $qrisStatic explicitly (e.g. Settings::get('qris_code')) or fallback to config.
     */
    public function create(int $amount, ?string $referenceId = null, ?int $expiryMinutes = null, ?string $qrisStatic = null): PayRelayPayment
    {
        return $this->createInternal($amount, $referenceId, $expiryMinutes, null, $qrisStatic);
    }

    /**
     * Create payment linked to a payable model (Transaction, Donation, etc).
     * Example: PayRelay::createFor($donation, 50000, qrisStatic: Settings::get('qris_code'))
     */
    public function createFor(Model $payable, int $amount, ?string $referenceId = null, ?int $expiryMinutes = null, ?string $qrisStatic = null): PayRelayPayment
    {
        return $this->createInternal($amount, $referenceId, $expiryMinutes, $payable, $qrisStatic);
    }

    protected function createInternal(int $amount, ?string $referenceId, ?int $expiryMinutes, ?Model $payable, ?string $qrisStatic): PayRelayPayment
    {
        $qrisStatic ??= config('pay-relay.qris_static');

        if (empty($qrisStatic)) {
            throw new \RuntimeException('QRIS statis kosong. Set PAY_RELAY_QRIS_STATIC atau pass $qrisStatic: Settings::get(\'qris_code\').');
        }

        $referenceId ??= (string) Str::ulid();
        $expiryMinutes ??= (int) config('pay-relay.expiry_minutes', 15);

        $model = static::model();

        $generated = UniqueCodeGenerator::generate($amount, function (int $finalAmount) use ($model): bool {
            return $model::where('amount_final', $finalAmount)
                ->where('status', PaymentStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->exists();
        });

        $qrisDynamic = QrisRelay::toDynamic($qrisStatic, $generated['final_amount']);

        $attributes = [
            'reference_id' => $referenceId,
            'amount_original' => $amount,
            'unique_code' => $generated['unique_code'],
            'amount_final' => $generated['final_amount'],
            'qris_static' => $qrisStatic,
            'qris_dynamic' => $qrisDynamic,
            'status' => PaymentStatus::Pending,
            'expires_at' => now()->addMinutes($expiryMinutes),
        ];

        if ($payable) {
            $attributes['payable_type'] = $payable->getMorphClass();
            $attributes['payable_id'] = $payable->getKey();
        }

        $payment = $model::create($attributes);

        event(new PaymentCreated($payment));

        return $payment;
    }

    public function find(string $referenceId): ?PayRelayPayment
    {
        return static::model()::where('reference_id', $referenceId)->first();
    }

    public function findOrFail(string $referenceId): PayRelayPayment
    {
        return static::model()::where('reference_id', $referenceId)->firstOrFail();
    }

    public function check(string $referenceId): PaymentStatus
    {
        return $this->findOrFail($referenceId)->status;
    }

    public function checkByAmount(int $finalAmount): ?PayRelayPayment
    {
        return static::model()::where('amount_final', $finalAmount)
            ->where('status', PaymentStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Expire overdue pending payments.
     * @return int number of expired rows
     */
    public function expireOverdue(): int
    {
        $model = static::model();
        $overdue = $model::overdue()->get();
        $count = 0;

        foreach ($overdue as $payment) {
            $payment->update(['status' => PaymentStatus::Expired]);
            event(new PaymentExpired($payment));
            $count++;
        }

        return $count;
    }
}
