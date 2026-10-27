<?php

use AdeRidwan\PayRelayPhp\Qris\QrisParser;
use AdeRidwan\PayRelayPhp\Qris\QrisRelay;
use AdeRidwan\PayRelayPhp\Support\Crc16;
use AdeRidwan\PayRelayPhp\Exceptions\InvalidQrisException;

it('parses and builds roundtrip', function () {
    $qris = qrisStatic();
    $tags = QrisParser::parse($qris);
    expect($tags)->toHaveKeys(['00', '01', '26']);
    $rebuilt = QrisParser::build($tags);
    expect($rebuilt)->toBe($qris);
});

it('throws on invalid CRC', function () {
    $qris = substr(qrisStatic(), 0, -4) . '0000';
    expect(fn () => QrisParser::parse($qris))->toThrow(InvalidQrisException::class);
});

it('relays static to dynamic with amount', function () {
    $static = qrisStatic();
    $dynamic = QrisRelay::toDynamic($static, 100123);
    expect(Crc16::verify($dynamic))->toBeTrue();
    $tags = QrisParser::parse($dynamic);
    expect($tags['54'])->toBe('100123');
    expect($tags['01'])->toBe('12');
});

it('throws on zero amount', function () {
    expect(fn () => QrisRelay::toDynamic(qrisStatic(), 0))->toThrow(InvalidQrisException::class);
});
