<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PuyuPe\SiproInternalApiLaravel\Http\Controllers\InternalV1Controller;
use PuyuPe\SiproInternalApiLaravel\Http\Middleware\VerifyHmac;

Route::prefix('internal/v1')
    ->middleware([VerifyHmac::class])
    ->group(function (): void {
        Route::post('/tenants', [InternalV1Controller::class, 'createTenant']);
        Route::post('/tenants/{tenant_uuid}:warn', [InternalV1Controller::class, 'warnTenant']);
        Route::post('/tenants/{tenant_uuid}:suspend', [InternalV1Controller::class, 'suspendTenant']);
        Route::post('/tenants/{tenant_uuid}:activate', [InternalV1Controller::class, 'activateTenant']);
    });
