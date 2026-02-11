<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Tests;

use PuyuPe\SiproInternalApiLaravel\Http\Middleware\VerifyHmac;
use Orchestra\Testbench\TestCase as Orchestra;
use PuyuPe\SiproInternalApiLaravel\SiproInternalApiLaravelServiceProvider;
use PuyuPe\SiproInternalApiLaravel\Tests\Fixtures\FakeTenantAdapter;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SiproInternalApiLaravelServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('sipro-internal-api-laravel.enabled', true);
        $app['config']->set('sipro-internal-api-laravel.adapter.class', FakeTenantAdapter::class);
        $app['config']->set('sipro-internal-api-laravel.hmac.nonce.enabled', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyHmac::class);
    }
}
