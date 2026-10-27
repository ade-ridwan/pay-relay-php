<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \AdeRidwan\PayRelayPhp\Models\PayRelayPayment create(int $amount, ?string $referenceId = null, ?int $expiryMinutes = null)
 * @method static \AdeRidwan\PayRelayPhp\Models\PayRelayPayment|null find(string $referenceId)
 * @method static \AdeRidwan\PayRelayPhp\Enums\PaymentStatus check(string $referenceId)
 * @method static \AdeRidwan\PayRelayPhp\Models\PayRelayPayment|null checkByAmount(int $finalAmount)
 * @method static int expireOverdue()
 */
class PayRelay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AdeRidwan\PayRelayPhp\PayRelay::class;
    }
}
