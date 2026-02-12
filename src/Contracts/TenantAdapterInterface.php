<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Contracts;

use PuyuPe\SiproInternalApiCore\Contracts\Dto\ActivateTenantRequest;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\CreateTenantRequest;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\SuspendTenantRequest;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\WarnTenantRequest;

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
