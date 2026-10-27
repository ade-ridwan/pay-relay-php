<?php

return [
    /*
    |--------------------------------------------------------------------------
    | QRIS Statis
    |--------------------------------------------------------------------------
    | String QRIS statis merchant (dari QR cetak). Wajib diisi via env.
    | Contoh: 000201010211...6304XXXX
    */
    'qris_static' => env('PAY_RELAY_QRIS_STATIC', ''),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    | Secret untuk HMAC SHA256 validasi webhook dari Android listener.
    | Header: X-Pay-Relay-Signature: hash_hmac('sha256', raw_body, secret)
    */
    'webhook_secret' => env('PAY_RELAY_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Kode Unik (suffix nominal)
    |--------------------------------------------------------------------------
    | final_amount = amount_original + unique_code
    | unique_code: 3 digit random 100-999, retry jika collision pending.
    */
    'unique_code' => [
        'length' => 3,
        'min' => 100,
        'max' => 999,
        'max_retry' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Expiry
    |--------------------------------------------------------------------------
    */
    'expiry_minutes' => 15,

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    */
    'route' => [
        'enabled' => true,
        'prefix' => 'api/pay-relay',
        'middleware' => ['api', 'throttle:60,1'],
        'webhook_path' => 'webhook',
        'status_path' => 'payments/{referenceId}/status',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Parser
    |--------------------------------------------------------------------------
    | Class untuk parsing amount & payload dari webhook Android.
    | Buat class sendiri di project (implement WebhookParserInterface)
    | jika field JSON tidak sesuai default (amount / amount_text).
    */
    'webhook' => [
        'parser' => \AdeRidwan\PayRelayPhp\Support\DefaultWebhookParser::class,
        'verify_signature' => env('PAY_RELAY_VERIFY_SIGNATURE', true),
        'signature_header' => env('PAY_RELAY_SIGNATURE_HEADER', 'X-Pay-Relay-Signature'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    | Class Eloquent untuk payment. Ganti jika ingin extend sendiri.
    | Default: PayRelayPayment (aman dari bentrok App\Models\Payment).
    */
    'model' => \AdeRidwan\PayRelayPhp\Models\PayRelayPayment::class,

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */
    'table' => 'pay_relay_payments',
];
