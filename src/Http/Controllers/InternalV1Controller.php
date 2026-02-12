<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Http\Controllers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ActivateTenantRequest;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\CreateTenantRequest;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\SuspendTenantRequest;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\WarnTenantRequest;
use PuyuPe\SiproInternalApiCore\Errors\ErrorCode;
use PuyuPe\SiproInternalApiCore\Errors\ErrorFactory;
use PuyuPe\SiproInternalApiCore\Errors\InternalApiError;
use PuyuPe\SiproInternalApiCore\Http\Response\ErrorResponse;
use PuyuPe\SiproInternalApiLaravel\Contracts\TenantAdapterInterface;
use PuyuPe\SiproInternalApiLaravel\Exceptions\TenantAdapterException;
use Throwable;

class InternalV1Controller
{
    public function __construct(private readonly Container $container)
    {
    }

    public function createTenant(Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(CreateTenantRequest::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $data = $this->adapter()->createTenant($dto);
        } catch (TenantAdapterException $exception) {
            return $this->adapterExceptionResponse($exception);
        } catch (Throwable $exception) {
            return $this->provisionFailedResponse($exception);
        }

        return response()->json([
            'ok' => true,
            'tenant_uuid' => $data['tenant_uuid'] ?? null,
            'status' => 'created',
            ...$data,
        ], 200);
    }

    public function warnTenant(string $tenant_uuid, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(WarnTenantRequest::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $this->adapter()->warnTenant($tenant_uuid, $dto);
        } catch (TenantAdapterException $exception) {
            return $this->adapterExceptionResponse($exception);
        } catch (Throwable $exception) {
            return $this->provisionFailedResponse($exception);
        }

        return response()->json([
            'ok' => true,
            'tenant_uuid' => $tenant_uuid,
            'status' => 'warn',
        ], 200);
    }

    public function suspendTenant(string $tenant_uuid, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(SuspendTenantRequest::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $this->adapter()->suspendTenant($tenant_uuid, $dto);
        } catch (TenantAdapterException $exception) {
            return $this->adapterExceptionResponse($exception);
        } catch (Throwable $exception) {
            return $this->provisionFailedResponse($exception);
        }

        return response()->json([
            'ok' => true,
            'tenant_uuid' => $tenant_uuid,
            'status' => 'suspended',
        ], 200);
    }

    public function activateTenant(string $tenant_uuid, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(ActivateTenantRequest::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $this->adapter()->activateTenant($tenant_uuid, $dto);
        } catch (TenantAdapterException $exception) {
            return $this->adapterExceptionResponse($exception);
        } catch (Throwable $exception) {
            return $this->provisionFailedResponse($exception);
        }

        return response()->json([
            'ok' => true,
            'tenant_uuid' => $tenant_uuid,
            'status' => 'active',
        ], 200);
    }

    private function buildValidatedDto(string $dtoClass, Request $request): mixed
    {
        try {
            /** @var array<string,mixed> $payload */
            $payload = $request->json()->all();
            $dto = $dtoClass::fromArray($payload);
            $validation = $dto->validate();

            if (method_exists($validation, 'ok') && $validation->ok() === false) {
                $validationError = ErrorFactory::validationError($validation);

                return response()->json(ErrorResponse::fromError($validationError)->toArray(), 400);
            }

            return $dto;
        } catch (InternalApiError $error) {
            return response()->json(ErrorResponse::fromError($error)->toArray(), 400);
        } catch (Throwable) {
            $fallbackError = new InternalApiError(ErrorCode::VALIDATION_ERROR, 'Invalid request payload.');

            return response()->json(ErrorResponse::fromError($fallbackError)->toArray(), 400);
        }
    }

    private function adapter(): TenantAdapterInterface
    {
        $adapter = $this->container->make(TenantAdapterInterface::class);

        if (!$adapter instanceof TenantAdapterInterface) {
            throw new TenantAdapterException(
                ErrorFactory::provisionFailed('Resolved adapter does not implement TenantAdapterInterface.')
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
