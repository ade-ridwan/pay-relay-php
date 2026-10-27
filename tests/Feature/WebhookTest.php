<?php

use AdeRidwan\PayRelayPhp\Events\PaymentPaid;
use AdeRidwan\PayRelayPhp\Laravel\Facades\PayRelay;
use Illuminate\Support\Facades\Event;

it('returns 422 for invalid amount', function () {
    $this->postJson('/api/pay-relay/webhook', [])
        ->assertStatus(422)->assertJson(['matched' => false]);
});

it('returns 404 for unmatched amount', function () {
    $this->postJson('/api/pay-relay/webhook', ['amount' => 999999])
        ->assertStatus(404)->assertJson(['matched' => false]);
});

it('matches pending payment via amount and marks paid', function () {
    Event::fake([PaymentPaid::class]);
    $pay = PayRelay::create(100000);

    $this->postJson('/api/pay-relay/webhook', ['amount' => $pay->amount_final])
        ->assertStatus(200)->assertJson(['matched' => true, 'reference_id' => $pay->reference_id]);

    expect($pay->fresh()->isPaid())->toBeTrue();
    Event::assertDispatched(PaymentPaid::class);
});

it('is idempotent for already paid', function () {
    Event::fake();
    $pay = PayRelay::create(50000);
    $this->postJson('/api/pay-relay/webhook', ['amount' => $pay->amount_final])->assertStatus(200);
    $this->postJson('/api/pay-relay/webhook', ['amount' => $pay->amount_final])
        ->assertStatus(200)->assertJson(['already_paid' => true]);
});

it('parses Rp text via AmountExtractor', function () {
    Event::fake();
    $pay = PayRelay::create(1000);
    // send text field instead of amount
    $this->postJson('/api/pay-relay/webhook', ['text' => 'Rp ' . number_format($pay->amount_final, 0, ',', '.')])
        ->assertStatus(200)->assertJson(['matched' => true]);
});

it('parses title/text field-agnostic and getContent fallback', function () {
    Event::fake();
    $pay = PayRelay::create(75000);
    // Simulate Android GoPay payload with title/text
    $payload = ['title' => 'Pembayaran QRIS statis diterima', 'text' => 'Rp ' . number_format($pay->amount_final, 0, ',', '.') . ' di ADE RIDWAN.'];
    $this->postJson('/api/pay-relay/webhook', $payload)
        ->assertStatus(200)->assertJson(['matched' => true]);
});

it('verifies signature when enabled', function () {
    config(['pay-relay.webhook.verify_signature' => true]);
    config(['pay-relay.webhook_secret' => 'secret1234567890123456789012345678']);
    $payload = json_encode(['amount' => 123456]);
    $sig = hash_hmac('sha256', $payload, 'secret1234567890123456789012345678');

    // without signature -> 401
    $this->call('POST', '/api/pay-relay/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload)
        ->assertStatus(401);

    // with valid signature -> 404 (amount not found) not 401
    $this->call('POST', '/api/pay-relay/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAY_RELAY_SIGNATURE' => $sig,
    ], $payload)->assertStatus(404);

    // custom header
    config(['pay-relay.webhook.signature_header' => 'X-Custom']);
    $this->call('POST', '/api/pay-relay/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_CUSTOM' => $sig,
    ], $payload)->assertStatus(404);
});

it('returns payment status', function () {
    $pay = PayRelay::create(60000);
    $this->getJson("/api/pay-relay/payments/{$pay->reference_id}/status")
        ->assertStatus(200)->assertJson(['reference_id' => $pay->reference_id, 'status' => 'pending']);
    $this->getJson('/api/pay-relay/payments/notfound/status')->assertStatus(404);
});
