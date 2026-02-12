<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel;

use Illuminate\Support\ServiceProvider;
use PuyuPe\SiproInternalApiLaravel\Contracts\TenantAdapterInterface;
use RuntimeException;

class SiproInternalApiLaravelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/sipro-internal-api-laravel.php',
            'sipro-internal-api-laravel'
        );

        $this->app->bind(TenantAdapterInterface::class, function ($app) {
            $configuredClass = config('sipro-internal-api-laravel.adapter.class');

            if (!is_string($configuredClass) || $configuredClass === '') {
                throw new RuntimeException('sipro-internal-api-laravel.adapter.class is not configured.');
            }

            return $app->make($configuredClass);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/sipro-internal-api-laravel.php' => config_path('sipro-internal-api-laravel.php'),
        ], 'sipro-internal-api-laravel-config');

        if ($this->app->environment(['local', 'testing'])) {
            $this->assertAdapterConfigIsValidForLocal();
        }

        if (config('sipro-internal-api-laravel.enabled') === true) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/internal.php');
        }
    }

    private function assertAdapterConfigIsValidForLocal(): void
    {
        $configuredClass = config('sipro-internal-api-laravel.adapter.class');

        if (!is_string($configuredClass) || $configuredClass === '') {
            throw new RuntimeException('sipro-internal-api-laravel.adapter.class must be configured in local/testing.');
        }

        if (!class_exists($configuredClass)) {
            throw new RuntimeException(sprintf('Configured adapter class does not exist: %s', $configuredClass));
        }

        if (!is_subclass_of($configuredClass, TenantAdapterInterface::class)) {
            throw new RuntimeException(sprintf(
                'Configured adapter class must implement %s: %s',
                TenantAdapterInterface::class,
                $configuredClass
            ));
        }
    }
}
