<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Support;

final class Crc16
{
    /**
     * CRC16-CCITT-FALSE (poly 0x1021, init 0xFFFF, refin=false, refout=false, xorout=0x0000)
     * Standard EMVCo QR uses this.
     */
    public static function calculate(string $data): string
    {
        $crc = 0xFFFF;

        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    public static function verify(string $qris): bool
    {
        // QRIS must contain 6304 + 4 hex chars at end
        if (strlen($qris) < 8 || substr($qris, -8, 4) !== '6304') {
            return false;
        }
        $payloadWithoutCrc = substr($qris, 0, -4);
        $expected = substr($qris, -4);
        $calculated = self::calculate($payloadWithoutCrc);

        return hash_equals($expected, $calculated);
    }
}
