<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Request;
use PuyuPe\SiproInternalApiCore\Errors\ErrorCode;
use PuyuPe\SiproInternalApiCore\Errors\ErrorFactory;
use PuyuPe\SiproInternalApiCore\Errors\InternalApiError;
use PuyuPe\SiproInternalApiCore\Http\InternalHeaders;
use PuyuPe\SiproInternalApiCore\Http\Response\ErrorResponse;
use PuyuPe\SiproInternalApiCore\Security\Hmac\HmacVerifier;
use PuyuPe\SiproInternalApiLaravel\Security\Hmac\LaravelCacheNonceStore;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VerifyHmac
{
    public function __construct(
        private readonly HmacVerifier $verifier,
        private readonly CacheFactory $cacheFactory
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $body = $request->getContent();
        $method = $request->getMethod();
        $path = $request->getPathInfo();

        $headers = [
            InternalHeaders::KEY_ID => (string) $request->header(InternalHeaders::KEY_ID),
            InternalHeaders::TIMESTAMP => (string) $request->header(InternalHeaders::TIMESTAMP),
            InternalHeaders::SIGNATURE => (string) $request->header(InternalHeaders::SIGNATURE),
            InternalHeaders::NONCE => (string) $request->header(InternalHeaders::NONCE),
        ];

        $missingHeaders = array_keys(array_filter([
            InternalHeaders::KEY_ID => $headers[InternalHeaders::KEY_ID] === '',
            InternalHeaders::TIMESTAMP => $headers[InternalHeaders::TIMESTAMP] === '',
            InternalHeaders::SIGNATURE => $headers[InternalHeaders::SIGNATURE] === '',
        ]));

        if ($missingHeaders !== []) {
            return $this->errorResponse(
                new InternalApiError(
                    ErrorCode::VALIDATION_ERROR,
                    'Missing required HMAC headers.',
                    ['missing_headers' => $missingHeaders]
                ),
                400
            );
        }

        if ($this->nonceEnabled() && $headers[InternalHeaders::NONCE] === '') {
            return $this->errorResponse(
                new InternalApiError(
                    ErrorCode::VALIDATION_ERROR,
                    'Missing required nonce header.',
                    ['missing_headers' => [InternalHeaders::NONCE]]
                ),
                400
            );
        }

        $secret = $this->resolveSecret($headers[InternalHeaders::KEY_ID]);

        if ($secret === null) {
            return $this->errorResponse($this->buildInvalidSignatureError(), 401);
        }

        try {
            $verification = $this->invokeVerifier(
                $method,
                $path,
                $headers,
                $body,
                $secret,
                $this->buildNonceStore(),
                $request
            );
        } catch (InternalApiError $error) {
            $normalized = $this->normalizeError($error);
            return $this->errorResponse($normalized, $this->statusFor($normalized));
        } catch (Throwable) {
            return $this->errorResponse($this->buildInvalidSignatureError(), 401);
        }

        if ($verification === false) {
            return $this->errorResponse($this->buildInvalidSignatureError(), 401);
        }

        return $next($request);
    }

    private function invokeVerifier(
        string $method,
        string $path,
        array $headers,
        string $body,
        string $secret,
        ?LaravelCacheNonceStore $nonceStore,
        Request $request
    ): mixed {
        $parameters = (new ReflectionMethod($this->verifier, 'verify'))->getParameters();

        $argumentsByName = [
            'method' => $method,
            'httpMethod' => $method,
            'path' => $path,
            'uriPath' => $path,
            'headers' => $headers,
            'headerMap' => $headers,
            'body' => $body,
            'rawBody' => $body,
            'secret' => $secret,
            'secretKey' => $secret,
            'keySecret' => $secret,
            'allowedClockSkewSeconds' => (int) config('sipro-internal-api-laravel.hmac.allowed_clock_skew_seconds', 300),
            'nonceStore' => $nonceStore,
            'nonceStorage' => $nonceStore,
            'nonceEnabled' => $this->nonceEnabled(),
            'request' => $request,
        ];

        $args = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $argumentsByName)) {
                $args[] = $argumentsByName[$name];
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
                continue;
            }

            $type = $parameter->getType();
            $typeName = $type !== null && method_exists($type, 'getName') ? $type->getName() : null;

            if ($typeName === Request::class) {
                $args[] = $request;
                continue;
            }

            if ($nonceStore !== null && $typeName !== null && is_a($nonceStore, $typeName)) {
                $args[] = $nonceStore;
                continue;
            }

            throw new InternalApiError(
                ErrorCode::VALIDATION_ERROR,
                sprintf('Unable to resolve HMAC verifier argument: %s', $name)
            );
        }

        return $this->verifier->verify(...$args);
    }

    private function resolveSecret(string $keyId): ?string
    {
        $keys = config('sipro-internal-api-laravel.hmac.keys', []);

        if (!is_array($keys)) {
            return null;
        }

        $secret = $keys[$keyId] ?? null;

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    private function nonceEnabled(): bool
    {
        return (bool) config('sipro-internal-api-laravel.hmac.nonce.enabled', true);
    }

    private function buildNonceStore(): ?LaravelCacheNonceStore
    {
        if (!$this->nonceEnabled()) {
            return null;
        }

        $store = config('sipro-internal-api-laravel.hmac.nonce.cache_store');
        $prefix = (string) config('sipro-internal-api-laravel.hmac.nonce.cache_prefix', 'sipro_internal_nonce:');

        return new LaravelCacheNonceStore($this->cacheFactory->store($store), $prefix);
    }


    private function normalizeError(InternalApiError $error): InternalApiError
    {
        return match ($this->extractErrorCode($error)) {
            ErrorCode::INVALID_SIGNATURE => $this->buildInvalidSignatureError(),
            ErrorCode::REQUEST_EXPIRED => $this->buildRequestExpiredError(),
            ErrorCode::NONCE_REPLAY => $this->buildNonceReplayError(),
            default => $error,
        };
    }

    private function statusFor(InternalApiError $error): int
    {
        $code = $this->extractErrorCode($error);

        return match ($code) {
            ErrorCode::VALIDATION_ERROR => 400,
            ErrorCode::REQUEST_EXPIRED,
            ErrorCode::NONCE_REPLAY,
            ErrorCode::INVALID_SIGNATURE => 401,
            default => 401,
        };
    }

    private function extractErrorCode(InternalApiError $error): ErrorCode
    {
        if (property_exists($error, 'code')) {
            /** @var mixed $value */
            $value = $error->code;
            if ($value instanceof ErrorCode) {
                return $value;
            }
        }

        if (method_exists($error, 'getErrorCode')) {
            $value = $error->getErrorCode();
            if ($value instanceof ErrorCode) {
                return $value;
            }
        }

        return ErrorCode::INVALID_SIGNATURE;
    }

    private function errorResponse(InternalApiError $error, int $status): Response
    {
        return response()->json(ErrorResponse::fromError($error)->toArray(), $status);
    }

    private function buildRequestExpiredError(): InternalApiError
    {
        if (method_exists(ErrorFactory::class, 'requestExpired')) {
            return ErrorFactory::requestExpired();
        }

        return new InternalApiError(ErrorCode::REQUEST_EXPIRED, 'Request expired.');
    }

    private function buildNonceReplayError(): InternalApiError
    {
        if (method_exists(ErrorFactory::class, 'nonceReplay')) {
            return ErrorFactory::nonceReplay();
        }

        return new InternalApiError(ErrorCode::NONCE_REPLAY, 'Nonce replay detected.');
    }

    private function buildInvalidSignatureError(): InternalApiError
    {
        if (method_exists(ErrorFactory::class, 'invalidSignature')) {
            return ErrorFactory::invalidSignature();
        }

        return new InternalApiError(ErrorCode::INVALID_SIGNATURE, 'Invalid signature.');
    }
}
