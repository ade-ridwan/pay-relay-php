<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Qris;

use AdeRidwan\PayRelayPhp\Exceptions\InvalidQrisException;
use AdeRidwan\PayRelayPhp\Support\Crc16;

final class QrisParser
{
    /**
     * Parse EMVCo TLV string into associative array Tag => Value.
     * Value for nested tags (26, 51, 62) is kept as raw string; caller may parse further.
     *
     * @return array<string, string>
     */
    public static function parse(string $qris): array
    {
        if (!Crc16::verify($qris)) {
            throw new InvalidQrisException('QRIS CRC16 verification failed.');
        }

        // Remove CRC tag for parsing (last 8 chars: 6304XXXX)
        $payload = substr($qris, 0, -8);

        $result = [];
        $i = 0;
        $len = strlen($payload);

        while ($i < $len) {
            if ($i + 4 > $len) {
                throw new InvalidQrisException("Malformed TLV at offset {$i}.");
            }
            $tag = substr($payload, $i, 2);
            $lengthStr = substr($payload, $i + 2, 2);

            if (!ctype_digit($tag) || !ctype_digit($lengthStr)) {
                throw new InvalidQrisException("Invalid TLV header at offset {$i}: tag={$tag} len={$lengthStr}");
            }

            $length = (int) $lengthStr;
            $value = substr($payload, $i + 4, $length);

            if (strlen($value) !== $length) {
                throw new InvalidQrisException("TLV value length mismatch at tag {$tag}.");
            }

            $result[$tag] = $value;
            $i += 4 + $length;
        }

        // Basic required tags for QRIS
        if (!isset($result['00']) || !isset($result['01'])) {
            throw new InvalidQrisException('QRIS missing required tags 00/01 (Payload Format / Point of Initiation).');
        }

        return $result;
    }

    /**
     * Build TLV string from array Tag => Value (sorted by tag).
     */
    public static function build(array $tags): string
    {
        ksort($tags);
        $payload = '';
        foreach ($tags as $tag => $value) {
            $payload .= $tag . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
        }
        // Append CRC placeholder then calculate
        $payloadWithCrcPlaceholder = $payload . '6304';
        $crc = Crc16::calculate($payloadWithCrcPlaceholder);

        return $payloadWithCrcPlaceholder . $crc;
    }

    /**
     * Parse nested TLV value (e.g. Tag 26, 62) into sub-tags.
     *
     * @return array<string, string>
     */
    public static function parseNested(string $value): array
    {
        $result = [];
        $i = 0;
        $len = strlen($value);
        while ($i < $len) {
            if ($i + 4 > $len) {
                break;
            }
            $tag = substr($value, $i, 2);
            $length = (int) substr($value, $i + 2, 2);
            $subValue = substr($value, $i + 4, $length);
            if (strlen($subValue) !== $length) {
                break;
            }
            $result[$tag] = $subValue;
            $i += 4 + $length;
        }

        return $result;
    }
}
