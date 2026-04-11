<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Tests\Fixtures;

use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionPayloadDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionResponseDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantExportRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantExportResponseDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantImportRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantImportResponseDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantLifecycleRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantLifecycleResponseDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantCloneAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantLifecycleAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantProvisioningAdapterInterface;

class FakeTenantAdapter implements TenantProvisioningAdapterInterface, TenantLifecycleAdapterInterface, TenantCloneAdapterInterface
{
    public static ?ProvisionPayloadDTO $lastCreateTenantRequest = null;
    public static ?TenantLifecycleRequestDTO $lastLifecycleRequest = null;
    public static ?TenantExportRequestDTO $lastExportTenantRequest = null;
    public static ?TenantImportRequestDTO $lastImportTenantRequest = null;

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

    public function warnTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO
    {
        self::$lastLifecycleRequest = $dto;

        return new TenantLifecycleResponseDTO($appKey, $dto->projectCode, 'debt', 'D');
    }

    public function suspendTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO
    {
        self::$lastLifecycleRequest = $dto;

        return new TenantLifecycleResponseDTO($appKey, $dto->projectCode, 'suspended', 'S');
    }

    public function activateTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO
    {
        self::$lastLifecycleRequest = $dto;

        return new TenantLifecycleResponseDTO($appKey, $dto->projectCode, 'normal', 'N');
    }

    public function closeTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO
    {
        self::$lastLifecycleRequest = $dto;

        return new TenantLifecycleResponseDTO($appKey, $dto->projectCode, 'closed', 'C');
    }

    public function reopenTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO
    {
        self::$lastLifecycleRequest = $dto;

        return new TenantLifecycleResponseDTO($appKey, $dto->projectCode, 'active', 'N');
    }

    public function exportTenant(string $appKey, TenantExportRequestDTO $dto): TenantExportResponseDTO
    {
        self::$lastExportTenantRequest = $dto;

        return new TenantExportResponseDTO($appKey, $dto->projectCode ?? '', 'exported', null, []);
    }

    public function importTenant(string $appKey, TenantImportRequestDTO $dto): TenantImportResponseDTO
    {
        self::$lastImportTenantRequest = $dto;

        return new TenantImportResponseDTO($appKey, $dto->projectCode ?? '', 'imported', null, []);
    }

    private function extractAppKey(ProvisionPayloadDTO $dto): string
    {
        $appKey = $dto->project->appKey;

        return $appKey !== null && $appKey !== '' ? $appKey : 'fake-app-key';
    }
}
