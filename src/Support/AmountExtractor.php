<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Support;

final class AmountExtractor
{
    /**
     * Extract amount_final from notification text.
     * Handles "Rp100.123", "Rp 100.123,00", "Terima Rp100.123 dari ..." etc.
     * Returns null if no amount found.
     */
    public static function fromText(mixed $text): ?int
    {
        if ($text === null || $text === '') {
            return null;
        }

        if (is_int($text)) {
            return $text;
        }

        if (is_float($text)) {
            return (int) $text;
        }

        if (!is_string($text)) {
            $text = (string) $text;
        }

        // Prioritas 1: cari "Rp" + angka (paling akurat, hindari saldo/tanggal)
        // Match Rp 100.123 | Rp100.123,00 | Rp. 100.123 | Rp_1_000 | Rp 1.000
        if (preg_match('/Rp\.?[\s_]*([\d\._]+(?:,\d{2})?)/iu', $text, $m)) {
            $digits = preg_replace('/[^0-9]/', '', $m[1]);
            if ($digits !== '' && $digits !== '0') {
                if (str_ends_with($m[1], ',00')) {
                    $digits = substr($digits, 0, -2);
                }
                return $digits !== '' ? (int) $digits : null;
            }
        }

        // Prioritas 2: jika text hanya angka + separator (tanpa Rp) dan mirip amount
        $trimmed = trim($text);
        if (preg_match('/^[\d\.,\s]+$/', $trimmed)) {
            $digits = preg_replace('/[^0-9]/', '', $trimmed);
            if ($digits !== '' && strlen($digits) >= 3) {
                return (int) $digits;
            }
        }

        return null;
    }

    /**
     * Extract amount from any payload value (int|string|null).
     */
    public static function fromMixed(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            if (ctype_digit(trim($value))) {
                return (int) $value;
            }

            return self::fromText($value);
        }

        return null;
    }

    /**
     * Try multiple fields in order, return first valid amount.
     *
     * @param array<string,mixed> $data
     * @param string[] $fields
     */
    public static function fromFields(array $data, array $fields = ['amount', 'amount_text', 'raw_message', 'message', 'text', 'body']): ?int
    {
        foreach ($fields as $field) {
            $value = data_get($data, $field);
            if ($value !== null) {
                $amount = self::fromMixed($value);
                if ($amount !== null && $amount > 0) {
                    return $amount;
                }
                $textAmount = self::fromText((string) $value);
                if ($textAmount !== null && $textAmount > 0) {
                    return $textAmount;
                }
            }
        }

        return null;
    }

    /**
     * Flatten all string values in payload recursively.
     *
     * @return string[]
     */
    public static function flattenStrings(array $data): array
    {
        $result = [];
        array_walk_recursive($data, function ($value) use (&$result) {
            if (is_string($value) && $value !== '') {
                $result[] = $value;
            } elseif (is_int($value) || is_float($value)) {
                $result[] = (string) $value;
            }
        });

        return $result;
    }

    /**
     * Scan all string values in payload for amount (field-agnostic).
     * Prioritizes strings containing "Rp".
     */
    public static function fromAnyPayload(array $payload): ?int
    {
        $strings = self::flattenStrings($payload);

        // Prioritas: string yang mengandung Rp
        foreach ($strings as $text) {
            if (stripos($text, 'Rp') !== false) {
                $amount = self::fromText($text);
                if ($amount !== null && $amount > 0) {
                    return $amount;
                }
            }
        }

        // Fallback: coba semua string
        foreach ($strings as $text) {
            $amount = self::fromText($text);
            if ($amount !== null && $amount > 0) {
                return $amount;
            }
            $mixed = self::fromMixed($text);
            if ($mixed !== null && $mixed > 0) {
                return $mixed;
            }
        }

        // Last: json_encode sebagai text
        $json = json_encode($payload);
        if ($json) {
            return self::fromText($json);
        }

        return null;
    }
}
