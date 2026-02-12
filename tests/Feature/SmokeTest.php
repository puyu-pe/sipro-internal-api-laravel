<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Tests\Feature;

use PuyuPe\SiproInternalApiLaravel\Tests\TestCase;

class SmokeTest extends TestCase
{
    public function test_package_loads(): void
    {
        $this->assertTrue($this->app->providerIsLoaded('PuyuPe\\SiproInternalApiLaravel\\SiproInternalApiLaravelServiceProvider'));
    }
}
