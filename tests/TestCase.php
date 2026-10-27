<?php

namespace Tests;

use AdeRidwan\PayRelayPhp\Laravel\PayRelayServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [PayRelayServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('pay-relay.qris_static', qrisStatic());
        $app['config']->set('pay-relay.webhook_secret', 'test-secret-32chars-long-secret-12345');
        $app['config']->set('pay-relay.webhook.verify_signature', false);
        $app['config']->set('pay-relay.table', 'pay_relay_payments');
        $app['config']->set('pay-relay.webhook.parser', \AdeRidwan\PayRelayPhp\Support\DefaultWebhookParser::class);
        $app['config']->set('pay-relay.webhook.signature_header', 'X-Pay-Relay-Signature');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('pay_relay_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id', 64)->unique();
            $table->nullableMorphs('payable');
            $table->unsignedBigInteger('amount_original');
            $table->string('unique_code', 10);
            $table->unsignedBigInteger('amount_final')->index();
            $table->text('qris_static');
            $table->text('qris_dynamic');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_notification')->nullable();
            $table->timestamps();
            $table->index(['amount_final', 'status']);
        });
    }
}
