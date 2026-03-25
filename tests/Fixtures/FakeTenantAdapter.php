<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Tests\Fixtures;

use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionActivateDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionPayloadDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionResponseDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionSuspendDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionWarnDebtDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantAdapterInterface;

class FakeTenantAdapter implements TenantAdapterInterface
{
    public static ?ProvisionPayloadDTO $lastCreateTenantRequest = null;

    public function createTenant(ProvisionPayloadDTO $dto): ProvisionResponseDTO
    {
        self::$lastCreateTenantRequest = $dto;

        return new ProvisionResponseDTO(
            appKey: $this->extractAppKey($dto),
            projectCode: $dto->project->code,
            database: 'yubus_dev_fake',
            status: 'created',
            provisionedAt: gmdate('c'),
            dbHost: null,
            migrated: true,
            seeded: true,
            systemParametersUpdated: true,
            usersCreated: 1,
            executionTimeMs: 10,
            warnings: []
        );
    }

    public function warnTenant(string $tenantUuid, ProvisionWarnDebtDTO $dto): void
    {
    }

    public function suspendTenant(string $tenantUuid, ProvisionSuspendDTO $dto): void
    {
    }

    public function activateTenant(string $tenantUuid, ProvisionActivateDTO $dto): void
    {
    }

    private function extractAppKey(ProvisionPayloadDTO $dto): string
    {
        $appKey = $dto->project->appKey;

        return $appKey !== null && $appKey !== '' ? $appKey : 'fake-app-key';
    }
}
