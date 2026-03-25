<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel;

use Illuminate\Support\ServiceProvider;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantCloneAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantLifecycleAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantProvisioningAdapterInterface;
use RuntimeException;

class SiproInternalApiLaravelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/sipro-internal-api-laravel.php',
            'sipro-internal-api-laravel'
        );

        $this->app->bind(TenantProvisioningAdapterInterface::class, function ($app) {
            $configuredClass = $this->resolveAdapterClass('provisioning_class');

            return $app->make($configuredClass);
        });

        $this->app->bind(TenantLifecycleAdapterInterface::class, function ($app) {
            $configuredClass = $this->resolveAdapterClass('lifecycle_class');

            return $app->make($configuredClass);
        });

        $this->app->bind(TenantCloneAdapterInterface::class, function ($app) {
            $configuredClass = $this->resolveAdapterClass('clone_class');

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
        $this->assertAdapterIsValid('provisioning_class', TenantProvisioningAdapterInterface::class);
        $this->assertAdapterIsValid('lifecycle_class', TenantLifecycleAdapterInterface::class);
        $this->assertAdapterIsValid('clone_class', TenantCloneAdapterInterface::class);
    }

    private function resolveAdapterClass(string $key): string
    {
        $configuredClass = config('sipro-internal-api-laravel.adapter.' . $key);

        if (!is_string($configuredClass) || $configuredClass === '') {
            throw new RuntimeException(sprintf('sipro-internal-api-laravel.adapter.%s is not configured.', $key));
        }

        return $configuredClass;
    }

    private function assertAdapterIsValid(string $key, string $interface): void
    {
        $configuredClass = $this->resolveAdapterClass($key);

        if (!class_exists($configuredClass)) {
            throw new RuntimeException(sprintf('Configured adapter class does not exist: %s', $configuredClass));
        }

        if (!is_subclass_of($configuredClass, $interface)) {
            throw new RuntimeException(sprintf(
                'Configured adapter class must implement %s: %s',
                $interface,
                $configuredClass
            ));
        }
    }
}
