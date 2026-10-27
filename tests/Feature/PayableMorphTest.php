<?php

use AdeRidwan\PayRelayPhp\Laravel\Facades\PayRelay;
use AdeRidwan\PayRelayPhp\Models\PayRelayPayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('donations', function (Blueprint $t) {
        $t->id(); $t->string('title'); $t->unsignedBigInteger('amount'); $t->timestamps();
    });
});

it('creates payable morph via createFor', function () {
    $donation = new class extends Model {
        use \AdeRidwan\PayRelayPhp\Concerns\HasPayRelayPayments;
        protected $table = 'donations'; protected $guarded = [];
    };
    $donation = $donation::create(['title' => 'Donasi', 'amount' => 50000]);

    $pay = PayRelay::createFor($donation, 50000, qrisStatic: qrisStatic());

    expect($pay->payable_type)->toBe($donation->getMorphClass());
    expect($pay->payable_id)->toBe($donation->getKey());
    expect($pay->payable->id)->toBe($donation->id);
    expect($donation->payRelayPayments()->count())->toBe(1);
    expect($donation->latestPayRelayPayment()->id)->toBe($pay->id);
});

it('still supports reference_id without payable', function () {
    $pay = PayRelay::create(10000, 'CUSTOM-REF');
    expect($pay->payable_type)->toBeNull();
    expect($pay->reference_id)->toBe('CUSTOM-REF');
});
