<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Http\Controllers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantCloneAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantImpersonationAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantLifecycleAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantProvisioningAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ImpersonableUserSearchRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ImpersonableUserSearchResponseDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ImpersonationRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ImpersonationResponseDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Validation\ValidationResult;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionPayloadDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionResponseDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantExportRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantExportResponseDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantImportRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantImportResponseDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantLifecycleRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantLifecycleResponseDTO;
use PuyuPe\SiproInternalApiCore\Errors\ErrorCode;
use PuyuPe\SiproInternalApiCore\Errors\ErrorFactory;
use PuyuPe\SiproInternalApiCore\Errors\InternalApiError;
use PuyuPe\SiproInternalApiCore\Http\Response\ErrorResponse;
use PuyuPe\SiproInternalApiLaravel\Exceptions\TenantAdapterException;
use Throwable;

class InternalV1Controller
{
    public function __construct(private readonly Container $container) {}

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

    public function warnTenant(string $resolveKey, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(TenantLifecycleRequestDTO::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $result = $this->lifecycleAdapter()->warnTenant($resolveKey, $dto);
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

    public function suspendTenant(string $resolveKey, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(TenantLifecycleRequestDTO::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $result = $this->lifecycleAdapter()->suspendTenant($resolveKey, $dto);
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

    public function activateTenant(string $resolveKey, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(TenantLifecycleRequestDTO::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $result = $this->lifecycleAdapter()->activateTenant($resolveKey, $dto);
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

    public function closeTenant(string $resolveKey, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(TenantLifecycleRequestDTO::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $result = $this->lifecycleAdapter()->closeTenant($resolveKey, $dto);
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

    public function reopenTenant(string $resolveKey, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(TenantLifecycleRequestDTO::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $result = $this->lifecycleAdapter()->reopenTenant($resolveKey, $dto);
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

    public function exportTenant(string $resolveKey, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(TenantExportRequestDTO::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $result = $this->cloneAdapter()->exportTenant($resolveKey, $dto);
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

    public function importTenant(string $resolveKey, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(TenantImportRequestDTO::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $result = $this->cloneAdapter()->importTenant($resolveKey, $dto);
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

    public function searchImpersonableUsers(string $resolveKey, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(ImpersonableUserSearchRequestDTO::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $result = $this->impersonationAdapter()->searchImpersonableUsers($resolveKey, $dto);
        } catch (TenantAdapterException $exception) {
            return $this->adapterExceptionResponse($exception);
        } catch (Throwable $exception) {
            return $this->provisionFailedResponse($exception);
        }

        $payload = $result instanceof ImpersonableUserSearchResponseDTO ? $result->toArray() : [];

        return response()->json([
            'ok' => true,
            ...$payload,
        ], 200);
    }

    public function impersonateUser(string $resolveKey, Request $request): JsonResponse
    {
        $dto = $this->buildValidatedDto(ImpersonationRequestDTO::class, $request);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        try {
            $result = $this->impersonationAdapter()->impersonateUser($resolveKey, $dto);
        } catch (TenantAdapterException $exception) {
            return $this->adapterExceptionResponse($exception);
        } catch (Throwable $exception) {
            return $this->provisionFailedResponse($exception);
        }

        $payload = $result instanceof ImpersonationResponseDTO ? $result->toArray() : [];

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
            $validation = $this->dtoValidationResult($dto);

            if ($validation !== null && $validation->ok() === false) {
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

    private function provisioningAdapter(): TenantProvisioningAdapterInterface
    {
        $adapter = $this->container->make(TenantProvisioningAdapterInterface::class);

        if (! $adapter instanceof TenantProvisioningAdapterInterface) {
            throw new TenantAdapterException(
                ErrorFactory::provisionFailed('Resolved adapter does not implement TenantProvisioningAdapterInterface.')
            );
        }

        return $adapter;
    }

    private function lifecycleAdapter(): TenantLifecycleAdapterInterface
    {
        $adapter = $this->container->make(TenantLifecycleAdapterInterface::class);

        if (! $adapter instanceof TenantLifecycleAdapterInterface) {
            throw new TenantAdapterException(
                ErrorFactory::provisionFailed('Resolved adapter does not implement TenantLifecycleAdapterInterface.')
            );
        }

        return $adapter;
    }

    private function cloneAdapter(): TenantCloneAdapterInterface
    {
        $adapter = $this->container->make(TenantCloneAdapterInterface::class);

        if (! $adapter instanceof TenantCloneAdapterInterface) {
            throw new TenantAdapterException(
                ErrorFactory::provisionFailed('Resolved adapter does not implement TenantCloneAdapterInterface.')
            );
        }

        return $adapter;
    }

    private function impersonationAdapter(): TenantImpersonationAdapterInterface
    {
        $adapter = $this->container->make(TenantImpersonationAdapterInterface::class);

        if (! $adapter instanceof TenantImpersonationAdapterInterface) {
            throw new TenantAdapterException(
                ErrorFactory::impersonationFailed('Resolved adapter does not implement TenantImpersonationAdapterInterface.')
            );
        }

        return $adapter;
    }

    private function adapterExceptionResponse(TenantAdapterException $exception): JsonResponse
    {
        $error = $exception->error();

        $status = match ($this->extractErrorCode($error)) {
            ErrorCode::VALIDATION_ERROR => 400,
            ErrorCode::TENANT_NOT_FOUND => 404,
            ErrorCode::USER_NOT_FOUND => 404,
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

    private function dtoValidationResult(object $dto): ?ValidationResult
    {
        if ($dto instanceof ImpersonationRequestDTO) {
            return $dto->validateDurationPolicy(
                $this->minImpersonationDurationMinutes(),
                $this->maxImpersonationDurationMinutes(),
            );
        }

        if ($dto instanceof ImpersonableUserSearchRequestDTO) {
            return $dto->validate();
        }

        if (method_exists($dto, 'validate')) {
            $validation = $dto->validate();

            return $validation instanceof ValidationResult ? $validation : null;
        }

        return null;
    }

    private function minImpersonationDurationMinutes(): int
    {
        return (int) config('sipro-internal-api-laravel.impersonation.duration.min_minutes', 5);
    }

    private function maxImpersonationDurationMinutes(): int
    {
        return (int) config('sipro-internal-api-laravel.impersonation.duration.max_minutes', 60);
    }
}
