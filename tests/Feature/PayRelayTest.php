<?php

use AdeRidwan\PayRelayPhp\Enums\PaymentStatus;
use AdeRidwan\PayRelayPhp\Events\PaymentCreated;
use AdeRidwan\PayRelayPhp\Events\PaymentExpired;
use AdeRidwan\PayRelayPhp\Laravel\Facades\PayRelay;
use AdeRidwan\PayRelayPhp\Models\PayRelayPayment;
use AdeRidwan\PayRelayPhp\Support\Crc16;
use Illuminate\Support\Facades\Event;

it('creates payment with QRIS dynamic', function () {
    Event::fake([PaymentCreated::class]);
    $pay = PayRelay::create(100000);

    expect($pay->reference_id)->not->toBeEmpty();
    expect($pay->amount_original)->toBe(100000);
    expect($pay->amount_final)->toBeGreaterThan(100000);
    expect($pay->status)->toBe(PaymentStatus::Pending);
    expect(Crc16::verify($pay->qris_dynamic))->toBeTrue();
    Event::assertDispatched(PaymentCreated::class);
});

it('creates with custom reference_id', function () {
    $pay = PayRelay::create(50000, 'TRX-123');
    expect($pay->reference_id)->toBe('TRX-123');
});

it('creates with explicit qrisStatic', function () {
    $customQris = qrisStatic();
    $pay = PayRelay::create(10000, null, null, $customQris);
    expect($pay->qris_static)->toBe($customQris);
});

it('checks status and expire', function () {
    Event::fake([PaymentExpired::class]);
    $pay = PayRelay::create(10000);
    expect(PayRelay::check($pay->reference_id))->toBe(PaymentStatus::Pending);

    // make overdue
    $pay->update(['expires_at' => now()->subMinutes(20)]);
    $count = PayRelay::expireOverdue();
    expect($count)->toBe(1);
    expect($pay->fresh()->status)->toBe(PaymentStatus::Expired);
    Event::assertDispatched(PaymentExpired::class);
});

it('finds by amount', function () {
    $pay = PayRelay::create(75000);
    $found = PayRelay::checkByAmount($pay->amount_final);
    expect($found->id)->toBe($pay->id);
});
