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

use PuyuPe\SiproInternalApiCore\Dto\ActivateTenantRequest;
use PuyuPe\SiproInternalApiCore\Dto\CreateTenantRequest;
use PuyuPe\SiproInternalApiCore\Dto\SuspendTenantRequest;
use PuyuPe\SiproInternalApiCore\Dto\WarnTenantRequest;
use PuyuPe\SiproInternalApiLaravel\Contracts\TenantAdapterInterface;
use PuyuPe\SiproInternalApiLaravel\Exceptions\TenantAdapterException;

final class SaaSTenantAdapter implements TenantAdapterInterface
{
    public function createTenant(CreateTenantRequest $dto): array
    {
        // 1) Validar reglas de negocio de tu SaaS.
        // 2) Crear tenant en BD/sistemas.
        // 3) Retornar payload adicional para response.
        return [
            'tenant_uuid' => 'generated-uuid',
        ];
    }

    public function warnTenant(string $tenantUuid, WarnTenantRequest $dto): void
    {
        // Marcar tenant como advertido.
    }

    public function suspendTenant(string $tenantUuid, SuspendTenantRequest $dto): void
    {
        // Suspender tenant.
    }

    public function activateTenant(string $tenantUuid, ActivateTenantRequest $dto): void
    {
        // Reactivar tenant.
    }
}
```

5. **Configurar `adapter.class` con el FQCN**

```php
'adapter' => [
    'class' => env('SIPRO_INTERNAL_API_ADAPTER_CLASS', App\InternalApi\Adapters\SaaSTenantAdapter::class),
],
```

En local/testing, si `adapter.class` está vacío, no existe o no implementa `TenantAdapterInterface`, el package lanza una excepción clara al boot para facilitar diagnóstico temprano.
