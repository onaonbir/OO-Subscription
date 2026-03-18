<?php

namespace App\Subscription;

use App\Subscription\Contracts\PaymentGateway;
use Illuminate\Support\ServiceProvider;

class SubscriptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/subscription.php', 'subscription');

        $handler = config('subscription.gateway.handler');

        if ($handler && class_exists($handler)) {
            $this->app->singleton(PaymentGateway::class, fn ($app) => $app->make($handler));
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ProcessSubscriptionsCommand::class,
                Console\SubscriptionStatusCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/subscription.php' => config_path('subscription.php'),
            ], 'subscription-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'subscription-migrations');
        }
    }
}
