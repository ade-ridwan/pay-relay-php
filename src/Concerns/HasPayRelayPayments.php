<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Concerns;

use AdeRidwan\PayRelayPhp\Models\PayRelayPayment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasPayRelayPayments
{
    public function payRelayPayments(): MorphMany
    {
        return $this->morphMany(PayRelayPayment::class, 'payable');
    }

    public function latestPayRelayPayment(): ?PayRelayPayment
    {
        return $this->payRelayPayments()->latest()->first();
    }
}
