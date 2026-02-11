<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Contracts;

use PuyuPe\SiproInternalApiCore\Dto\ActivateTenantRequest;
use PuyuPe\SiproInternalApiCore\Dto\CreateTenantRequest;
use PuyuPe\SiproInternalApiCore\Dto\SuspendTenantRequest;
use PuyuPe\SiproInternalApiCore\Dto\WarnTenantRequest;

interface TenantAdapterInterface
{
    /**
     * @return array<string,mixed>
     */
    public function createTenant(CreateTenantRequest $dto): array;

    public function warnTenant(string $tenantUuid, WarnTenantRequest $dto): void;

    public function suspendTenant(string $tenantUuid, SuspendTenantRequest $dto): void;

    public function activateTenant(string $tenantUuid, ActivateTenantRequest $dto): void;
}
