# pay-relay-php

Relay **QRIS Statis** menjadi **QRIS Dinamis** dengan kode unik di nominal. Verifikasi pembayaran via **webhook custom** dari aplikasi **Android Notification Listener** yang menangkap notifikasi mutasi masuk (BCA, BRI, Mandiri, DANA, GoPay Merchant, dll).

> Cocok untuk UMKM / warung yang cuma punya 1 QRIS cetak tapi ingin checkout otomatis di web.

[![PHP ^8.1](https://img.shields.io/badge/PHP-^8.1-blue)]()
[![Laravel 10+](https://img.shields.io/badge/Laravel-10%7C11%7C12%7C13-red)]()
[![License MIT](https://img.shields.io/badge/license-MIT-green)]()

---

## Fitur

- **QRIS Relay** — Parse TLV EMVCo, inject Tag `54` (amount) + ubah `01` jadi `12` (dinamis), hitung ulang CRC16-CCITT `6304`.
- **Kode Unik Nominal** — `final = original + 100..999` (3 digit suffix). Contoh `100.000` → `100.123`. Anti-tabrakan untuk transaksi `pending`.
- **Storage Database** — `pay_relay_payments` (`reference_id` ULID, `payable_type/id` morph, `amount_final` indexed, `status: pending|paid|expired|cancelled`). Model `PayRelayPayment` (alias `Payment` deprecated).
- **Payable Morph** — Hubungkan ke `Donation` / `Transaction` via `HasPayRelayPayments` trait + `PayRelay::createFor($model, amount)`.
- **Webhook Android** — `POST /api/pay-relay/webhook` HMAC header customizable, `verify_signature` bisa di-bypass untuk test, parser `WebhookParserInterface` + `AmountExtractor` field-agnostic (`title/text/ticker` apapun).
- **Cek Status** — `GET /api/pay-relay/payments/{referenceId}/status` untuk polling FE.
- **Expiry** — `php artisan pay-relay:expire` + events `PaymentCreated` / `PaymentPaid` / `PaymentExpired`.
- **Secret Generator** — `php artisan pay-relay:secret`.

---

## Instalasi

```bash
composer require ade-ridwan/pay-relay-php

php artisan vendor:publish --tag=pay-relay-config
php artisan vendor:publish --tag=pay-relay-migrations
# untuk payable morph di DB lama:
php artisan vendor:publish --tag=pay-relay-payable
```

Isi `.env`:

```bash
# generate otomatis
php artisan pay-relay:secret
# atau manual
# PAY_RELAY_QRIS_STATIC=000201010211...6304XXXX
# PAY_RELAY_WEBHOOK_SECRET=isi-minimal-32-karakter-random-di-sini
# PAY_RELAY_VERIFY_SIGNATURE=true
# PAY_RELAY_SIGNATURE_HEADER=X-Pay-Relay-Signature
```

Lalu:

```bash
php artisan migrate
```

Scheduler (disarankan):

```php
// app/Console/Kernel.php atau routes/console.php
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
        'verify_signature' => env('PAY_RELAY_VERIFY_SIGNATURE', true),
        'signature_header' => env('PAY_RELAY_SIGNATURE_HEADER', 'X-Pay-Relay-Signature'),
    ],
    'model' => \AdeRidwan\PayRelayPhp\Models\PayRelayPayment::class,
    'table' => 'pay_relay_payments',
];
```

> `PayRelayPayment` sengaja tidak pakai nama `Payment` agar tidak bentrok `App\Models\Payment`. `webhook.signature_header` bisa dikustom (mis. `X-Custom-Signature`).

---

## Penggunaan Dasar

```php
use AdeRidwan\PayRelayPhp\Laravel\Facades\PayRelay;

// 1. Buat pembayaran (tanpa payable)
$payment = PayRelay::create(amount: 100000);
// $payment->reference_id  => "01H..." (ULID, untuk polling)
// $payment->amount_original => 100000
// $payment->unique_code   => "123"
// $payment->amount_final  => 100123
// $payment->qris_dynamic  => "000201010212..." (string QR siap render)
// $payment->expires_at    => Carbon +15 menit

// 1b. Dengan Donation/Transaction (payable morph)
use App\Models\Donation;
$donation = Donation::find(1);
$payment = PayRelay::createFor($donation, 50000);
// $payment->payable_type => "App\Models\Donation", payable_id => 1
// $payment->payable => Donation model

// Ambil qris untuk FE
$qris = $payment->qris_dynamic;

// 2. Tampilkan ke FE
return response()->json([
    'reference_id' => $payment->reference_id,
    'amount_final' => $payment->amount_final,
    'qris'         => $payment->qris_dynamic, // render jadi QR image di FE
]);

// 3. Polling status
$status = PayRelay::check($payment->reference_id); // PaymentStatus enum
// atau HTTP: GET /api/pay-relay/payments/{referenceId}/status

// 4. Listen event
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

### Ambil PayRelay dari Donation

```php
use App\Models\Donation;

$donation = Donation::find(1);
$latest = $donation->latestPayRelayPayment(); // helper trait
$qris = $latest?->qris_dynamic;

$all = $donation->payRelayPayments; // Collection
$pending = $donation->payRelayPayments()->where('status','pending')->first();
```

Pasang trait di model:

```php
use AdeRidwan\PayRelayPhp\Concerns\HasPayRelayPayments;

class Donation extends Model { use HasPayRelayPayments; }
```

---

## Integrasi `transactions` / `Donation`

### Opsi A — Simple (`reference_id` sebagai FK)

```php
$tx = Transaction::create(['amount' => 100000, 'status' => 'pending']);
$pay = PayRelay::create(amount: $tx->amount, referenceId: (string) $tx->id);
// GET /api/pay-relay/payments/123/status

Event::listen(PaymentPaid::class, function (PaymentPaid $e) {
    Transaction::where('id', $e->payment->reference_id)->update([
        'status'  => 'paid',
        'paid_at' => $e->payment->paid_at,
    ]);
});
```

### Opsi B — Payable Morph (Recommended)

```php
$donation = Donation::create(['title'=>'Donasi Masjid','amount'=>50000]);
$pay = PayRelay::createFor($donation, $donation->amount);

// listener generik
Event::listen(PaymentPaid::class, function (PaymentPaid $e) {
    if ($e->payment->payable instanceof \App\Models\Donation) {
        $e->payment->payable->update(['status'=>'paid','paid_at'=>$e->payment->paid_at]);
    }
});
```

**Rambu:** `reference_id` tetap unique — jangan `create()` 2x untuk id sama sebelum expired.

---

## Webhook Android

```
POST /api/pay-relay/webhook
Content-Type: application/json
X-Pay-Relay-Signature: hmac_sha256(raw_body, PAY_RELAY_WEBHOOK_SECRET)
# header name bisa dikustom via PAY_RELAY_SIGNATURE_HEADER
```

**Test tanpa signature (bypass):**
```env
PAY_RELAY_VERIFY_SIGNATURE=false
```

Payload fleksibel (field `title/text/ticker` apapun, di-scan rekursif):

```json
{
  "title": "Pembayaran QRIS statis diterima",
  "text": "Rp 1.000 di ADE RIDWAN.",
  "package": "com.gojek.gopaymerchant",
  "timestamp": "2026-10-27T13:28:00.155+07:00"
}
```

**Aturan controller:**
- `amount` diekstrak via `AmountExtractor` (`Rp` regex `Rp\.?[\s_]*([\d\._]+)`) — tahan `Rp 1.000`, `Rp_1_000`, `Rp1.000,00`, plus `getContent()` fallback jika `all()` kosong.
- Cari `pay_relay_payments` dengan `amount_final = amount` + `status=pending` + `expires_at > now()` → update `paid`.
- Jika sudah `paid` → `200 {already_paid:true}` (idempotent).
- Jika tidak ketemu → `404` (Android bisa retry).
- Jika `amount` tidak ter-parse → `422`.

**Custom parsing di project:**

```php
// app/Parsers/MyQrisParser.php
use AdeRidwan\PayRelayPhp\Contracts\WebhookParserInterface;
use AdeRidwan\PayRelayPhp\Support\AmountExtractor;
use Illuminate\Http\Request;

class MyQrisParser implements WebhookParserInterface {
    public function parseAmount(Request $request): ?int {
        return AmountExtractor::fromAnyPayload($request->all());
        // atau: AmountExtractor::fromText($request->input('custom.sms'))
    }
    public function parsePayload(Request $request): array {
        return $request->all();
    }
}
// config/pay-relay.php
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
  "payable_type": "App\\Models\\Donation",
  "payable_id": 1,
  "status": "pending",
  "amount_original": 50000,
  "amount_final": 50412,
  "unique_code": "412",
  "qris_dynamic": "000201...",
  "expires_at": "2026-10-27T10:15:00+07:00",
  "paid_at": null
}
```

---

## Events & Command

```php
use AdeRidwan\PayRelayPhp\Events\{PaymentCreated, PaymentPaid, PaymentExpired};
Event::listen(PaymentPaid::class, fn($e) => SendWa::dispatch($e->payment));
```

```bash
php artisan pay-relay:expire                # expire overdue
php artisan pay-relay:secret                # generate PAY_RELAY_WEBHOOK_SECRET
php artisan pay-relay:secret --show         # tampilkan saja
php artisan pay-relay:secret --length=32 --force
```

Event `PaymentPaid` listener daftar di `AppServiceProvider::boot()` atau `bootstrap/app.php ->withEvents(discover: [...])` dengan `#[Listen]`.

---

## Alur Lengkap

```
[Merchant QRIS Statis .env] -> PayRelay::createFor($donation, 50000) -> 50412 + QRIS Dinamis (Tag 54 & CRC)
-> Customer scan & bayar Rp50.412 -> HP merchant notif "Rp 50.412"
-> Android POST /webhook {text:"Rp 50.412", signature} -> AmountExtractor -> match pending -> paid -> PaymentPaid
-> FE polling /status -> "paid" -> Donation update paid_at via listener
```

---

## Keamanan

- `PAY_RELAY_WEBHOOK_SECRET` minimal 32 karakter (generate via `pay-relay:secret`), jangan commit.
- Header signature customizable via `PAY_RELAY_SIGNATURE_HEADER`.
- Webhook wajib HMAC `hash_equals`, middleware `VerifyWebhookSignature`.
- Rate limit `throttle:60,1` default.
- Jangan expose `qris_static` ke response publik.

---

## Roadmap

- **V1.0** — Relay QRIS, kode unik, DB payable morph, webhook parser + AmountExtractor, status, expire, secret command.
- **V1.1** — `pay_relay_logs`, dashboard Filament.
- **V1.2** — Multi-merchant, notifikasi WA/Telegram.

---

## Lisensi

MIT — Ade Ridwan.
