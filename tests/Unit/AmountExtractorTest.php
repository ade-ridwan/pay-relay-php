<?php

use AdeRidwan\PayRelayPhp\Support\AmountExtractor;

it('extracts Rp with dot', fn () => expect(AmountExtractor::fromText('Rp100.123'))->toBe(100123));
it('extracts Rp with space', fn () => expect(AmountExtractor::fromText('Rp 1.000'))->toBe(1000));
it('extracts Rp with underscore', fn () => expect(AmountExtractor::fromText('Rp_1_000'))->toBe(1000));
it('extracts Rp with comma decimal', fn () => expect(AmountExtractor::fromText('Rp 1.000,00'))->toBe(1000));

it('extracts first Rp in long text with saldo', function () {
    $text = 'Berhasil! Anda menerima Rp100.123 dari BUDI Saldo Rp5.250.000';
    expect(AmountExtractor::fromText($text))->toBe(100123);
});

it('extracts from any payload field-agnostic', function () {
    expect(AmountExtractor::fromAnyPayload(['title' => 'BCA', 'text' => 'Rp50.842']))->toBe(50842);
    expect(AmountExtractor::fromAnyPayload(['unknown' => 'Rp200.123']))->toBe(200123);
    expect(AmountExtractor::fromAnyPayload(['nested' => ['msg' => 'Rp300.123']]))->toBe(300123);
});

it('returns null for no amount', fn () => expect(AmountExtractor::fromText('Hello world'))->toBeNull());
