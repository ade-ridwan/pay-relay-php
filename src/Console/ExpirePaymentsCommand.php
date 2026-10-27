<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Console;

use AdeRidwan\PayRelayPhp\PayRelay;
use Illuminate\Console\Command;

class ExpirePaymentsCommand extends Command
{
    protected $signature = 'pay-relay:expire';
    protected $description = 'Expire overdue pending pay-relay payments';

    public function handle(PayRelay $payRelay): int
    {
        $count = $payRelay->expireOverdue();
        $this->info("Expired {$count} payment(s).");

        return self::SUCCESS;
    }
}
