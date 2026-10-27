<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Events;

use AdeRidwan\PayRelayPhp\Models\PayRelayPayment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly PayRelayPayment $payment) {}
}
