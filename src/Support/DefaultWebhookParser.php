<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Support;

use AdeRidwan\PayRelayPhp\Contracts\WebhookParserInterface;
use Illuminate\Http\Request;

class DefaultWebhookParser implements WebhookParserInterface
{
    public function parseAmount(Request $request): ?int
    {
        // 1. Jika amount numeric clean -> langsung pakai
        $rawAmount = $request->input('amount');
        if (is_numeric($rawAmount) && (int) $rawAmount > 0) {
            return (int) $rawAmount;
        }

        // 2. Prioritaskan getContent() JSON (paling reliable, tahan charset & all() aneh)
        $raw = $request->getContent();
        if ($raw !== '' && $raw !== null) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $fromFields = AmountExtractor::fromFields($decoded, [
                    'amount',
                    'amount_text',
                    'raw_message',
                    'message',
                    'text',
                    'title',
                    'ticker',
                    'bigText',
                    'subText',
                    'body',
                    'notification.body',
                    'data.amount',
                    'data.nominal',
                ]);
                if ($fromFields !== null) {
                    return $fromFields;
                }
                $fromAny = AmountExtractor::fromAnyPayload($decoded);
                if ($fromAny !== null) {
                    return $fromAny;
                }
            }
            // Fallback raw string
            $fromText = AmountExtractor::fromText($raw);
            if ($fromText !== null) {
                return $fromText;
            }
        }

        // 3. Handle kasus all() jadi ['{json}' => ''] (JSON jadi key)
        $all = $request->all();
        if (count($all) === 1) {
            $key = array_key_first($all);
            if (is_string($key) && str_starts_with(trim($key), '{')) {
                $maybe = json_decode($key, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($maybe)) {
                    $fromAny = AmountExtractor::fromAnyPayload($maybe);
                    if ($fromAny !== null) {
                        return $fromAny;
                    }
                }
                $fromText = AmountExtractor::fromText($key);
                if ($fromText !== null) {
                    return $fromText;
                }
            }
        }

        // 4. Fallback all() normal + scan semua field
        $fromFields = AmountExtractor::fromFields($all, [
            'amount',
            'amount_text',
            'raw_message',
            'message',
            'text',
            'title',
            'ticker',
            'bigText',
            'subText',
            'body',
            'notification.body',
            'data.amount',
            'data.nominal',
        ]);
        if ($fromFields !== null) {
            return $fromFields;
        }

        $fromAny = AmountExtractor::fromAnyPayload($all);
        if ($fromAny !== null) {
            return $fromAny;
        }

        return null;
    }

    public function parsePayload(Request $request): array
    {
        $raw = $request->getContent();
        if ($raw !== '' && $raw !== null) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        $all = $request->all();
        if (count($all) === 1) {
            $key = array_key_first($all);
            if (is_string($key) && str_starts_with(trim($key), '{')) {
                $maybe = json_decode($key, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($maybe)) {
                    return $maybe;
                }
            }
        }

        return $all;
    }
}
