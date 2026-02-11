<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Tests\Fixtures;

use PuyuPe\SiproInternalApiCore\Dto\ActivateTenantRequest;
use PuyuPe\SiproInternalApiCore\Dto\CreateTenantRequest;
use PuyuPe\SiproInternalApiCore\Dto\SuspendTenantRequest;
use PuyuPe\SiproInternalApiCore\Dto\WarnTenantRequest;
use PuyuPe\SiproInternalApiLaravel\Contracts\TenantAdapterInterface;

class FakeTenantAdapter implements TenantAdapterInterface
{
    public function createTenant(CreateTenantRequest $dto): array
    {
        return [
            'tenant_uuid' => 'fake-tenant-uuid',
            'source' => 'fake-adapter',
        ];
    }

    public function warnTenant(string $tenantUuid, WarnTenantRequest $dto): void
    {
    }

    public function suspendTenant(string $tenantUuid, SuspendTenantRequest $dto): void
    {
    }

    public function activateTenant(string $tenantUuid, ActivateTenantRequest $dto): void
    {
    }
}
