<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isPending(): bool { return $this === self::Pending; }
    public function isPaid(): bool { return $this === self::Paid; }
    public function isExpired(): bool { return $this === self::Expired; }
}
