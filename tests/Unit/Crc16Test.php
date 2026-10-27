<?php

use AdeRidwan\PayRelayPhp\Support\Crc16;

it('calculates CRC16-CCITT-FALSE for known vector', function () {
    expect(Crc16::calculate('123456789'))->toBe('29B1');
});

it('verifies valid QRIS CRC', function () {
    $qris = qrisStatic();
    expect(Crc16::verify($qris))->toBeTrue();
});

it('fails verification for tampered QRIS', function () {
    $qris = qrisStatic();
    $tampered = substr($qris, 0, -1) . '0';
    expect(Crc16::verify($tampered))->toBeFalse();
});

it('calculates CRC with correct padding', function () {
    expect(Crc16::calculate(''))->toBe('FFFF');
});
