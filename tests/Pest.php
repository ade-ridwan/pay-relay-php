<?php

uses(Tests\TestCase::class)->in('Feature');
uses()->in('Unit');

function qrisStatic(): string {
    return \AdeRidwan\PayRelayPhp\Qris\QrisParser::build([
        '00'=>'01',
        '01'=>'11',
        '26'=>'0012ID.CO.QRIS.WWW0118ID1234567890128',
        '52'=>'0000',
        '53'=>'360',
        '58'=>'ID',
        '59'=>'TEST MERCHANT',
        '60'=>'JAKARTA',
        '61'=>'12345',
    ]);
}
