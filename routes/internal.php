<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PuyuPe\SiproInternalApiLaravel\Http\Controllers\InternalV1Controller;
use PuyuPe\SiproInternalApiLaravel\Http\Middleware\VerifyHmac;

Route::prefix('internal/v1')
    ->middleware([VerifyHmac::class])
    ->group(function (): void {
        Route::post('/tenants', [InternalV1Controller::class, 'createTenant']);
        Route::post('/tenants/{appKey}:warn', [InternalV1Controller::class, 'warnTenant']);
        Route::post('/tenants/{appKey}:suspend', [InternalV1Controller::class, 'suspendTenant']);
        Route::post('/tenants/{appKey}:activate', [InternalV1Controller::class, 'activateTenant']);
        Route::post('/tenants/{appKey}:close', [InternalV1Controller::class, 'closeTenant']);
        Route::post('/tenants/{appKey}:reopen', [InternalV1Controller::class, 'reopenTenant']);
        Route::post('/tenants/{appKey}:export', [InternalV1Controller::class, 'exportTenant']);
        Route::post('/tenants/{appKey}:import', [InternalV1Controller::class, 'importTenant']);
        Route::post('/tenants/{appKey}:impersonate', [InternalV1Controller::class, 'impersonateUser']);
    });
