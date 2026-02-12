<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Tests\Fixtures;

use PuyuPe\SiproInternalApiCore\Contracts\Dto\ActivateTenantRequest;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\CreateTenantRequest;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\SuspendTenantRequest;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\WarnTenantRequest;
use PuyuPe\SiproInternalApiLaravel\Contracts\TenantAdapterInterface;

class FakeTenantAdapter implements TenantAdapterInterface
{
    public static ?CreateTenantRequest $lastCreateTenantRequest = null;

    public function createTenant(CreateTenantRequest $dto): array
    {
        self::$lastCreateTenantRequest = $dto;

        return [
            'tenant_uuid' => $this->extractTenantUuid($dto),
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

    private function extractTenantUuid(CreateTenantRequest $dto): string
    {
        if (property_exists($dto, 'tenant_uuid')) {
            /** @var mixed $tenantUuid */
            $tenantUuid = $dto->tenant_uuid;
            if (is_string($tenantUuid) && $tenantUuid !== '') {
                return $tenantUuid;
            }
        }

        if (property_exists($dto, 'tenantUuid')) {
            /** @var mixed $tenantUuid */
            $tenantUuid = $dto->tenantUuid;
            if (is_string($tenantUuid) && $tenantUuid !== '') {
                return $tenantUuid;
            }
        }

        if (method_exists($dto, 'toArray')) {
            $data = $dto->toArray();
            if (is_array($data) && isset($data['tenant_uuid']) && is_string($data['tenant_uuid'])) {
                return $data['tenant_uuid'];
            }
        }

        return 'fake-tenant-uuid';
    }
}
