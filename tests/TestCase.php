<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use PuyuPe\SiproInternalApiLaravel\SiproInternalApiLaravelServiceProvider;
use PuyuPe\SiproInternalApiLaravel\Tests\Fixtures\FakeTenantAdapter;

abstract class TestCase extends Orchestra
{
    protected string $keyId = 'test-key-id';
    protected string $secret = 'test-secret-value';

    protected function getPackageProviders($app): array
    {
        return [
            SiproInternalApiLaravelServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.env', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('sipro-internal-api-laravel.enabled', true);
        $app['config']->set('sipro-internal-api-laravel.adapter.provisioning_class', FakeTenantAdapter::class);
        $app['config']->set('sipro-internal-api-laravel.adapter.lifecycle_class', FakeTenantAdapter::class);
        $app['config']->set('sipro-internal-api-laravel.adapter.clone_class', FakeTenantAdapter::class);
        $app['config']->set('sipro-internal-api-laravel.adapter.impersonation_class', FakeTenantAdapter::class);
        $app['config']->set('sipro-internal-api-laravel.hmac.allowed_clock_skew_seconds', 300);
        $app['config']->set('sipro-internal-api-laravel.hmac.keys', [
            $this->keyId => $this->secret,
        ]);
        $app['config']->set('sipro-internal-api-laravel.hmac.nonce.enabled', false);
        $app['config']->set('sipro-internal-api-laravel.hmac.nonce.cache_store', 'array');
        $app['config']->set('sipro-internal-api-laravel.hmac.nonce.cache_prefix', 'test_nonce:');
        $app['config']->set('sipro-internal-api-laravel.impersonation.duration.default_minutes', 5);
        $app['config']->set('sipro-internal-api-laravel.impersonation.duration.min_minutes', 5);
        $app['config']->set('sipro-internal-api-laravel.impersonation.duration.max_minutes', 60);
    }
}
