# pay-relay-php

Relay **QRIS Statis** menjadi **QRIS Dinamis** dengan kode unik di nominal — tanpa payment gateway mahal. Verifikasi pembayaran via **webhook custom** dari aplikasi **Android Notification Listener** yang menangkap notifikasi mutasi masuk (BCA, BRI, Mandiri, DANA, dll).

> Cocok untuk UMKM / warung yang cuma punya 1 QRIS cetak tapi ingin checkout otomatis di web.

[![PHP ^8.1](https://img.shields.io/badge/PHP-^8.1-blue)]()
[![Laravel 10+](https://img.shields.io/badge/Laravel-10%2B-red)]()
[![License MIT](https://img.shields.io/badge/license-MIT-green)]()

---

## Fitur

- **QRIS Relay** — Parse TLV EMVCo, inject Tag `54` (amount) + ubah `01` jadi `12` (dinamis), hitung ulang CRC16-CCITT `6304`.
- **Kode Unik Nominal** — `final = original + 100..999` (3 digit suffix). Contoh `100.000` → `100.123`. Anti-tabrakan untuk transaksi `pending`.
- **Storage Database** — `pay_relay_payments` (`reference_id` ULID, `amount_final` indexed, `status: pending|paid|expired|cancelled`).
- **Webhook Android** — `POST /api/pay-relay/webhook` dengan HMAC `X-Pay-Relay-Signature`, cocokkan `amount_final` → update `paid` (idempotent).
- **Cek Status** — `GET /api/pay-relay/payments/{referenceId}/status` untuk polling FE.
- **Expiry** — `php artisan pay-relay:expire` + events `PaymentCreated` / `PaymentPaid` / `PaymentExpired`.

---

## Instalasi

```bash
composer require ade-ridwan/pay-relay-php

php artisan vendor:publish --tag=pay-relay-config
php artisan vendor:publish --tag=pay-relay-migrations
```

Isi `.env`:

```env
PAY_RELAY_QRIS_STATIC=000201010211...6304XXXX
PAY_RELAY_WEBHOOK_SECRET=isi-minimal-32-karakter-random-di-sini
```

Lalu:

```bash
php artisan migrate
```

Tambahkan scheduler (opsional tapi disarankan):

```php
// app/Console/Kernel.php
$schedule->command('pay-relay:expire')->everyMinute();
```

---

## Konfigurasi

`config/pay-relay.php`:

```php
return [
    'qris_static'    => env('PAY_RELAY_QRIS_STATIC', ''),
    'webhook_secret' => env('PAY_RELAY_WEBHOOK_SECRET', ''),
    'unique_code'    => ['length'=>3, 'min'=>100, 'max'=>999, 'max_retry'=>10],
    'expiry_minutes' => 15,
    'route' => [
        'enabled'      => true,
        'prefix'       => 'api/pay-relay',
        'middleware'   => ['api', 'throttle:60,1'],
        'webhook_path' => 'webhook',
        'status_path'  => 'payments/{referenceId}/status',
    ],
    'webhook' => [
        'parser' => \AdeRidwan\PayRelayPhp\Support\DefaultWebhookParser::class,
    ],
    'model' => \AdeRidwan\PayRelayPhp\Models\PayRelayPayment::class,
    'table' => 'pay_relay_payments',
];
```

> Model `PayRelayPayment` sengaja tidak pakai nama `Payment` agar tidak bentrok dengan `App\Models\Payment` milikmu. Alias lama `Payment` masih ada (deprecated) untuk BC.

---

## Penggunaan Dasar

```php
use AdeRidwan\PayRelayPhp\Laravel\Facades\PayRelay;

// 1. Buat pembayaran
$payment = PayRelay::create(amount: 100000);
// $payment->reference_id  => "01H..." (ULID, untuk polling)
// $payment->amount_original => 100000
// $payment->unique_code   => "123"
// $payment->amount_final  => 100123
// $payment->qris_dynamic  => "000201010212..." (string QR siap render)
// $payment->expires_at    => Carbon +15 menit

// 2. Tampilkan ke FE
return response()->json([
    'reference_id' => $payment->reference_id,
    'amount_final' => $payment->amount_final,
    'qris'         => $payment->qris_dynamic, // render jadi QR image di FE
]);

// 3. Polling status
$status = PayRelay::check($payment->reference_id); // PaymentStatus enum
// atau via HTTP: GET /api/pay-relay/payments/{referenceId}/status
// => {status:"pending", amount_final:100123, expires_at: "..."}

// 4. Listen event untuk update order
use AdeRidwan\PayRelayPhp\Events\PaymentPaid;
Event::listen(PaymentPaid::class, fn(PaymentPaid $e) => logger("paid: ".$e->payment->reference_id));
```

### Low-level (tanpa DB)

```php
use AdeRidwan\PayRelayPhp\Qris\QrisRelay;
use AdeRidwan\PayRelayPhp\Support\Crc16;

$qrisDinamis = QrisRelay::toDynamic($staticQris, 100123);
Crc16::verify($qrisDinamis); // true
```

---

## Integrasi dengan `transactions` Milikmu (Opsi A)

`reference_id` (`varchar 64 unique`) di `PayRelayPayment` memang disiapkan sebagai **correlation ID** ke tabelmu.

```php
// 1. Buat transaksi milikmu dulu
$tx = Transaction::create(['amount' => 100000, 'status' => 'pending']);

// 2. Pakai PK transactions sebagai reference_id (cast string)
$pay = PayRelay::create(amount: $tx->amount, referenceId: (string) $tx->id);

// 3. FE polling pakai $tx->id
// GET /api/pay-relay/payments/123/status

// 4. Sinkron balik saat webhook paid
use AdeRidwan\PayRelayPhp\Events\PaymentPaid;

Event::listen(PaymentPaid::class, function (PaymentPaid $e) {
    Transaction::where('id', $e->payment->reference_id)->update([
        'status'  => 'paid',
        'paid_at' => $e->payment->paid_at,
    ]);
});
```

**Rambu:**
- `reference_id` harus unik — jangan `create()` 2x untuk `tx.id` yang sama sebelum expired. Untuk retry pakai `"$tx->id-retry-1"` atau batalkan dulu.
- Jika PK sensitif, tetap pakai ULID internal (`PayRelay::create(100000)`) dan simpan mapping `transactions.pay_relay_ref = $pay->reference_id`.

---

## Webhook Android

Aplikasi Android kamu (Notification Listener) harus POST ke:

```
POST /api/pay-relay/webhook
Content-Type: application/json
X-Pay-Relay-Signature: hmac_sha256(raw_body, PAY_RELAY_WEBHOOK_SECRET)
```

Payload:

```json
{
  "amount": 100123,
  "amount_text": "Rp100.123",
  "raw_message": "QRIS BCA: Terima Rp100.123 dari ...",
  "package_name": "com.bca.mobile",
  "posted_at": "2026-10-27T10:00:00+07:00"
}
```

**Aturan controller:**
- `amount` dinormalisasi (`Rp100.123` → `100123`) via parser (`Contracts/WebhookParserInterface`).
- Cari `pay_relay_payments` dengan `amount_final = amount` + `status=pending` + `expires_at > now()` → update `paid`.
- Jika sudah `paid` → response `200 {already_paid:true}` (idempotent).
- Jika tidak ketemu → `404` (Android bisa retry).

**Custom parsing di project pengguna:**
```php
// app/Parsers/MyQrisParser.php
use AdeRidwan\PayRelayPhp\Contracts\WebhookParserInterface;
use AdeRidwan\PayRelayPhp\Support\UniqueCodeGenerator;
use Illuminate\Http\Request;

class MyQrisParser implements WebhookParserInterface {
    public function parseAmount(Request $request): ?int {
        // contoh field beda: data.nominal / sms.body
        return UniqueCodeGenerator::normalizeAmount(
            $request->input('data.nominal') ?? $request->input('sms.body') ?? $request->input('amount')
        );
    }
    public function parsePayload(Request $request): array {
        return $request->all(); // simpan ke raw_notification
    }
}

// config/pay-relay.php (publish dulu)
'webhook' => ['parser' => \App\Parsers\MyQrisParser::class],
```

---

## API Routes

| Method | URL | Keterangan |
|---|---|---|
| `POST` | `/api/pay-relay/webhook` | Webhook Android (HMAC) |
| `GET` | `/api/pay-relay/payments/{referenceId}/status` | Cek status polling |

Response status:

```json
{
  "reference_id": "01HXYZ",
  "status": "pending",
  "amount_original": 100000,
  "amount_final": 100123,
  "unique_code": "123",
  "qris_dynamic": "000201...",
  "expires_at": "2026-10-27T10:15:00+07:00",
  "paid_at": null
}
```

---

## Events & Command

```php
use AdeRidwan\PayRelayPhp\Events\{PaymentCreated, PaymentPaid, PaymentExpired};

// Kirim WA / Telegram saat paid
Event::listen(PaymentPaid::class, fn($e) => SendWa::dispatch($e->payment));
```

```bash
php artisan pay-relay:expire   # expire yang overdue
```

---

## Alur Lengkap

```
[Merchant QRIS Statis .env] -> PayRelay::create(100000) -> 100123 + QRIS Dinamis (Tag 54 & CRC)
-> Customer scan & bayar Rp100.123 -> HP merchant notif "Terima Rp100.123"
-> Android POST /webhook {amount:100123, signature} -> match pending -> paid -> event
-> FE polling /status -> "paid" -> Transaction update
```

---

## Keamanan

- `PAY_RELAY_WEBHOOK_SECRET` minimal 32 karakter, jangan commit.
- Webhook wajib HMAC `hash_equals`, sudah ada `VerifyWebhookSignature` middleware.
- Rate limit `throttle:60,1` default.
- Jangan expose `qris_static` ke response publik.

---

## Roadmap

- **V1.0** — MVP sekarang (relay, kode unik, DB, webhook, status, expire).
- **V1.1** — `pay_relay_logs`, morph `payable_type/id` untuk multi-merchant.
- **V1.2** — Dashboard Filament, notifikasi WA/Telegram.

---

## Lisensi

MIT — Ade Ridwan.

Lihat `PRD.md` untuk spesifikasi lengkap dan `AGENTS.md` untuk panduan development.
