<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Contracts;

use Illuminate\Http\Request;

interface WebhookParserInterface
{
    /**
     * Parse amount_final from webhook request.
     * Return null if not found / invalid.
     */
    public function parseAmount(Request $request): ?int;

    /**
     * Parse payload to store in raw_notification.
     * Default is $request->all().
     *
     * @return array<string, mixed>
     */
    public function parsePayload(Request $request): array;
}
