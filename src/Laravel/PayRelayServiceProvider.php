<?php

declare(strict_types=1);

namespace AdeRidwan\PayRelayPhp\Laravel;

use AdeRidwan\PayRelayPhp\Console\ExpirePaymentsCommand;
use AdeRidwan\PayRelayPhp\Http\Controllers\PaymentStatusController;
use AdeRidwan\PayRelayPhp\Http\Controllers\WebhookController;
use AdeRidwan\PayRelayPhp\Http\Middleware\VerifyWebhookSignature;
use AdeRidwan\PayRelayPhp\PayRelay;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PayRelayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/pay-relay.php', 'pay-relay');

        $this->app->singleton(PayRelay::class, fn () => new PayRelay());
        $this->app->alias(PayRelay::class, 'pay-relay');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/pay-relay.php' => config_path('pay-relay.php'),
        ], 'pay-relay-config');

        $this->publishes([
            __DIR__ . '/../../database/migrations/create_pay_relay_payments_table.php.stub' => database_path('migrations/' . date('Y_m_d_His') . '_create_pay_relay_payments_table.php'),
        ], 'pay-relay-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([ExpirePaymentsCommand::class]);
        }

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        if (!config('pay-relay.route.enabled', true)) {
            return;
        }

        $prefix = config('pay-relay.route.prefix', 'api/pay-relay');
        $middleware = config('pay-relay.route.middleware', ['api']);
        $webhookPath = config('pay-relay.route.webhook_path', 'webhook');
        $statusPath = config('pay-relay.route.status_path', 'payments/{referenceId}/status');

        Route::prefix($prefix)
            ->middleware($middleware)
            ->group(function () use ($webhookPath, $statusPath) {
                Route::post($webhookPath, [WebhookController::class, 'handle'])
                    ->middleware(VerifyWebhookSignature::class)
                    ->name('pay-relay.webhook');

                Route::get($statusPath, [PaymentStatusController::class, 'show'])
                    ->name('pay-relay.status');
            });
    }
}
