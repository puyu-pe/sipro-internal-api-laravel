<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Http\Controllers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionPayloadDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionResponseDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantLifecycleRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantLifecycleResponseDTO;
use PuyuPe\SiproInternalApiCore\Errors\ErrorCode;
use PuyuPe\SiproInternalApiCore\Errors\ErrorFactory;
use PuyuPe\SiproInternalApiCore\Errors\InternalApiError;
use PuyuPe\SiproInternalApiCore\Http\Response\ErrorResponse;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantLifecycleAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantProvisioningAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantCloneAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantExportRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantExportResponseDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantImportRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantImportResponseDTO;
use PuyuPe\SiproInternalApiLaravel\Exceptions\TenantAdapterException;
use Throwable;

class InternalV1Controller
{
    public function __construct(private readonly Container $container)
    {
    }

    public function createTenant(Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(ProvisionPayloadDTO::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $result = $this->provisioningAdapter()->createTenant($dto);
        } catch (TenantAdapterException $exception) {
            return $this->adapterExceptionResponse($exception);
        } catch (Throwable $exception) {
            return $this->provisionFailedResponse($exception);
        }

        $payload = $result instanceof ProvisionResponseDTO ? $result->toArray() : [];

        return response()->json([
            'ok' => true,
            'status' => $payload['status'] ?? 'created',
            ...$payload,
        ], 200);
    }

    public function warnTenant(string $appKey, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(TenantLifecycleRequestDTO::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $result = $this->lifecycleAdapter()->warnTenant($appKey, $dto);
        } catch (TenantAdapterException $exception) {
            return $this->adapterExceptionResponse($exception);
        } catch (Throwable $exception) {
            return $this->provisionFailedResponse($exception);
        }

        $payload = $result instanceof TenantLifecycleResponseDTO ? $result->toArray() : [];

        return response()->json([
            'ok' => true,
            ...$payload,
        ], 200);
    }

    public function suspendTenant(string $appKey, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(TenantLifecycleRequestDTO::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $result = $this->lifecycleAdapter()->suspendTenant($appKey, $dto);
        } catch (TenantAdapterException $exception) {
            return $this->adapterExceptionResponse($exception);
        } catch (Throwable $exception) {
            return $this->provisionFailedResponse($exception);
        }

        $payload = $result instanceof TenantLifecycleResponseDTO ? $result->toArray() : [];

        return response()->json([
            'ok' => true,
            ...$payload,
        ], 200);
    }

    public function activateTenant(string $appKey, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(TenantLifecycleRequestDTO::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $result = $this->lifecycleAdapter()->activateTenant($appKey, $dto);
        } catch (TenantAdapterException $exception) {
            return $this->adapterExceptionResponse($exception);
        } catch (Throwable $exception) {
            return $this->provisionFailedResponse($exception);
        }

        $payload = $result instanceof TenantLifecycleResponseDTO ? $result->toArray() : [];

        return response()->json([
            'ok' => true,
            ...$payload,
        ], 200);
    }

    public function exportTenant(string $appKey, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(TenantExportRequestDTO::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $result = $this->cloneAdapter()->exportTenant($appKey, $dto);
        } catch (TenantAdapterException $exception) {
            return $this->adapterExceptionResponse($exception);
        } catch (Throwable $exception) {
            return $this->provisionFailedResponse($exception);
        }

        $payload = $result instanceof TenantExportResponseDTO ? $result->toArray() : [];

        return response()->json([
            'ok' => true,
            ...$payload,
        ], 200);
    }

    public function importTenant(string $appKey, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(TenantImportRequestDTO::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $result = $this->cloneAdapter()->importTenant($appKey, $dto);
        } catch (TenantAdapterException $exception) {
            return $this->adapterExceptionResponse($exception);
        } catch (Throwable $exception) {
            return $this->provisionFailedResponse($exception);
        }

        $payload = $result instanceof TenantImportResponseDTO ? $result->toArray() : [];

        return response()->json([
            'ok' => true,
            ...$payload,
        ], 200);
    }

    private function buildValidatedDto(string $dtoClass, Request $request): mixed
    {
        try {
            /** @var array<string,mixed> $payload */
            $payload = $request->json()->all();
            $dto = $dtoClass::fromArray($payload);
            if (method_exists($dto, 'validate')) {
                $validation = $dto->validate();
                if (method_exists($validation, 'ok') && $validation->ok() === false) {
                    $validationError = ErrorFactory::validationError($validation);

                    return response()->json(ErrorResponse::fromError($validationError)->toArray(), 400);
                }
            }

            return $dto;
        } catch (InternalApiError $error) {
            return response()->json(ErrorResponse::fromError($error)->toArray(), 400);
        } catch (Throwable) {
            $fallbackError = new InternalApiError(ErrorCode::VALIDATION_ERROR, 'Invalid request payload.');

            return response()->json(ErrorResponse::fromError($fallbackError)->toArray(), 400);
        }
    }

    private function provisioningAdapter(): TenantProvisioningAdapterInterface
    {
        $adapter = $this->container->make(TenantProvisioningAdapterInterface::class);

        if (!$adapter instanceof TenantProvisioningAdapterInterface) {
            throw new TenantAdapterException(
                ErrorFactory::provisionFailed('Resolved adapter does not implement TenantProvisioningAdapterInterface.')
            );
        }

        return $adapter;
    }

    private function lifecycleAdapter(): TenantLifecycleAdapterInterface
    {
        $adapter = $this->container->make(TenantLifecycleAdapterInterface::class);

        if (!$adapter instanceof TenantLifecycleAdapterInterface) {
            throw new TenantAdapterException(
                ErrorFactory::provisionFailed('Resolved adapter does not implement TenantLifecycleAdapterInterface.')
            );
        }

        return $adapter;
    }

    private function cloneAdapter(): TenantCloneAdapterInterface
    {
        $adapter = $this->container->make(TenantCloneAdapterInterface::class);

        if (!$adapter instanceof TenantCloneAdapterInterface) {
            throw new TenantAdapterException(
                ErrorFactory::provisionFailed('Resolved adapter does not implement TenantCloneAdapterInterface.')
            );
        }

        return $adapter;
    }

    private function adapterExceptionResponse(TenantAdapterException $exception): JsonResponse
    {
        $error = $exception->error();

        $status = match ($this->extractErrorCode($error)) {
            ErrorCode::TENANT_NOT_FOUND => 404,
            ErrorCode::TENANT_ALREADY_EXISTS => 409,
            default => 500,
        };

        return response()->json(ErrorResponse::fromError($error)->toArray(), $status);
    }

    private function provisionFailedResponse(Throwable $exception): JsonResponse
    {
        $error = ErrorFactory::provisionFailed($exception->getMessage(), [
            'exception' => $exception::class,
        ]);

        return response()->json(ErrorResponse::fromError($error)->toArray(), 500);
    }


    private function extractErrorCode(InternalApiError $error): ErrorCode
    {
        if (property_exists($error, 'code')) {
            /** @var mixed $code */
            $code = $error->code;

            if ($code instanceof ErrorCode) {
                return $code;
            }
        }

        if (method_exists($error, 'getErrorCode')) {
            $code = $error->getErrorCode();

            if ($code instanceof ErrorCode) {
                return $code;
            }
        }

        return ErrorCode::PROVISION_FAILED;
    }
}
