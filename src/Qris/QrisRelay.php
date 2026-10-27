<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Qris;

use AdeRidwan\PayRelayPhp\Exceptions\InvalidQrisException;

final class QrisRelay
{
    /**
     * Convert QRIS Statis to Dinamis by injecting finalAmount into Tag 54 and regenerating CRC.
     *
     * @param string $staticQris Full QRIS string (with CRC)
     * @param int    $finalAmount Final amount in IDR (e.g. 100123) — will be formatted as string for Tag 54
     */
    public static function toDynamic(string $staticQris, int $finalAmount): string
    {
        if ($finalAmount <= 0) {
            throw new InvalidQrisException('Final amount must be > 0.');
        }

        $tags = QrisParser::parse($staticQris);

        // QRIS relay: keep 00 = 01, ensure 01 = 12 (dynamic) if needed
        // 01 = Point of Initiation Method: 11 static, 12 dynamic
        $tags['01'] = '12';

        // Tag 54 = Transaction Amount
        // EMVCo: amount as string without currency symbol, up to 13 chars incl dot
        // For IDR, we send integer as string e.g. "100123.00" or "100123"
        // Use integer string to avoid locale issues; spec allows "100123"
        $tags['54'] = (string) $finalAmount;

        // Rebuild with sorted tags + new CRC
        return QrisParser::build($tags);
    }
}
