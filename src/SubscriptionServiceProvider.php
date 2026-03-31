<?php

namespace OnaOnbir\Subscription;

use Illuminate\Support\ServiceProvider;
use OnaOnbir\Subscription\Contracts\PaymentGateway;

class SubscriptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/subscription.php', 'subscription');

        $this->app->singleton(PaymentGateway::class, function ($app) {
            $handler = config('subscription.gateway.handler');

            if (! $handler || ! class_exists($handler)) {
                throw new \RuntimeException('No payment gateway handler configured. Set subscription.gateway.handler in your config.');
            }

            return $app->make($handler);
        });
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
