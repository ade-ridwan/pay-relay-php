<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Support;

use AdeRidwan\PayRelayPhp\Exceptions\UniqueCodeExhaustedException;

final class UniqueCodeGenerator
{
    /**
     * Generate unique code suffix for amount.
     *
     * @param int      $amount      Original amount (e.g. 100000)
     * @param callable $existsCheck function(int $finalAmount): bool — returns true if collision
     * @return array{unique_code: string, final_amount: int}
     */
    public static function generate(int $amount, ?callable $existsCheck = null): array
    {
        $length = (int) config('pay-relay.unique_code.length', 3);
        $min = (int) config('pay-relay.unique_code.min', (int) str_repeat('1', $length) === 0 ? 100 : (int) pow(10, $length - 1));
        $max = (int) config('pay-relay.unique_code.max', (int) pow(10, $length) - 1);
        $maxRetry = (int) config('pay-relay.unique_code.max_retry', 10);

        // Fallback if config missing
        if ($min === 0 || $max === 0) {
            $min = (int) pow(10, $length - 1);
            $max = (int) pow(10, $length) - 1;
        }

        for ($i = 0; $i < $maxRetry; $i++) {
            $code = (string) random_int($min, $max);
            $finalAmount = $amount + (int) $code;

            if ($existsCheck === null || !$existsCheck($finalAmount)) {
                return [
                    'unique_code' => $code,
                    'final_amount' => $finalAmount,
                ];
            }
        }

        throw new UniqueCodeExhaustedException(
            "Failed to generate unique code after {$maxRetry} retries for amount {$amount}."
        );
    }

    /**
     * Normalize amount from various formats: "Rp100.123", "100,123", "100123" -> 100123
     */
    public static function normalizeAmount(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (!is_string($value)) {
            return null;
        }
        $digits = preg_replace('/[^0-9]/', '', $value);
        if ($digits === '' || $digits === null) {
            return null;
        }

        return (int) $digits;
    }
}
