<?php

use AdeRidwan\PayRelayPhp\Support\UniqueCodeGenerator;
use AdeRidwan\PayRelayPhp\Exceptions\UniqueCodeExhaustedException;

it('generates unique code in range', function () {
    $result = UniqueCodeGenerator::generate(100000, fn () => false);
    expect($result['unique_code'])->toMatch('/^\d{3}$/');
    expect((int) $result['unique_code'])->toBeBetween(100, 999);
    expect($result['final_amount'])->toBe(100000 + (int) $result['unique_code']);
});

it('retries on collision', function () {
    $calls = 0;
    $result = UniqueCodeGenerator::generate(50000, function () use (&$calls) {
        $calls++;
        return $calls < 3;
    });
    expect($calls)->toBe(3);
    expect($result['final_amount'])->toBeGreaterThan(50000);
});

it('throws when exhausted', function () {
    expect(fn () => UniqueCodeGenerator::generate(1000, fn () => true))
        ->toThrow(UniqueCodeExhaustedException::class);
});

it('normalizes amount strings', function () {
    expect(UniqueCodeGenerator::normalizeAmount('Rp100.123'))->toBe(100123);
    expect(UniqueCodeGenerator::normalizeAmount('100,123'))->toBe(100123);
    expect(UniqueCodeGenerator::normalizeAmount(100123))->toBe(100123);
    expect(UniqueCodeGenerator::normalizeAmount('abc'))->toBeNull();
});
