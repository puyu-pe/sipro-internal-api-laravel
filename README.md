# puyu-pe/sipro-internal-api-laravel

Bridge Laravel 12 para exponer `/internal/v1` reutilizando `puyu-pe/sipro-internal-api-core`.

## Integración en un SaaS Laravel 12

> No requiere tocar `bootstrap/app.php` (usa package autodiscovery).

1. **Instalar paquete**

```bash
composer require puyu-pe/sipro-internal-api-laravel
```

2. **Publicar configuración**

```bash
php artisan vendor:publish --tag=sipro-internal-api-laravel-config
```

3. **Configurar llaves HMAC**

En `.env`, define tus secretos y luego mapea `keyId => secret` en `config/sipro-internal-api-laravel.php`.

Ejemplo de enfoque:

```php
'hmac' => [
    'keys' => [
        'saas-main' => env('SIPRO_INTERNAL_API_HMAC_SECRET_MAIN'),
        'saas-rotated' => env('SIPRO_INTERNAL_API_HMAC_SECRET_ROTATED'),
    ],
],
```

4. **Implementar adapter de tenant en tu SaaS**

Skeleton sugerido:

```php
<?php

declare(strict_types=1);

namespace App\InternalApi\Adapters;

use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantLifecycleAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantProvisioningAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionPayloadDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionResponseDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantLifecycleRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantLifecycleResponseDTO;
use PuyuPe\SiproInternalApiLaravel\Exceptions\TenantAdapterException;

final class SaaSProvisioningAdapter implements TenantProvisioningAdapterInterface
{
    public function createTenant(ProvisionPayloadDTO $dto): ProvisionResponseDTO
    {
        // 1) Validar reglas de negocio de tu SaaS.
        // 2) Crear tenant en BD/sistemas.
        // 3) Retornar payload adicional para response.
        return new ProvisionResponseDTO(
            appKey: $dto->project->appKey ?? '',
            projectCode: $dto->project->code,
            database: 'db_name',
            status: 'created',
            provisionedAt: gmdate('c'),
            dbHost: null,
            migrated: false,
            seeded: false,
            systemParametersUpdated: false,
            usersCreated: 0,
            executionTimeMs: 0,
            warnings: []
        );
    }
}

final class SaaSLifecycleAdapter implements TenantLifecycleAdapterInterface
{
    public function warnTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO
    {
        return new TenantLifecycleResponseDTO($appKey, $dto->projectCode, 'debt', 'D');
    }

    public function suspendTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO
    {
        return new TenantLifecycleResponseDTO($appKey, $dto->projectCode, 'suspended', 'S');
    }

    public function activateTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO
    {
        return new TenantLifecycleResponseDTO($appKey, $dto->projectCode, 'normal', 'N');
    }
}
```

5. **Configurar `adapter.class` con el FQCN**

```php
'adapter' => [
    'provisioning_class' => env('SIPRO_INTERNAL_API_PROVISIONING_ADAPTER_CLASS', App\InternalApi\Adapters\SaaSProvisioningAdapter::class),
    'lifecycle_class' => env('SIPRO_INTERNAL_API_LIFECYCLE_ADAPTER_CLASS', App\InternalApi\Adapters\SaaSLifecycleAdapter::class),
],
```

En local/testing, si `adapter.provisioning_class` o `adapter.lifecycle_class` está vacío, no existe o no implementa su interfaz, el package lanza una excepción clara al boot para facilitar diagnóstico temprano.
