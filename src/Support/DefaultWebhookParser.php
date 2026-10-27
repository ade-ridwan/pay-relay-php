<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Support;

use AdeRidwan\PayRelayPhp\Contracts\WebhookParserInterface;
use Illuminate\Http\Request;

class DefaultWebhookParser implements WebhookParserInterface
{
    public function parseAmount(Request $request): ?int
    {
        $amount = UniqueCodeGenerator::normalizeAmount($request->input('amount'));

        if ($amount === null) {
            $amount = UniqueCodeGenerator::normalizeAmount($request->input('amount_text'));
        }

        return $amount;
    }

    public function parsePayload(Request $request): array
    {
        return $request->all();
    }
}
