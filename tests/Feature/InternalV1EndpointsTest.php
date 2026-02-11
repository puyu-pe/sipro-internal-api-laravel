<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Tests\Feature;

use PuyuPe\SiproInternalApiCore\Http\InternalHeaders;
use PuyuPe\SiproInternalApiCore\Security\Hmac\HmacSigner;
use PuyuPe\SiproInternalApiLaravel\Tests\Fixtures\FakeTenantAdapter;
use PuyuPe\SiproInternalApiLaravel\Tests\TestCase;
use ReflectionMethod;
use RuntimeException;

class InternalV1EndpointsTest extends TestCase
{
    public function test_routes_are_loaded(): void
    {
        $response = $this->call('POST', '/internal/v1/tenants', [], [], [], [], '{}');

        $this->assertNotSame(404, $response->getStatusCode());
    }

    public function test_reject_missing_hmac_headers(): void
    {
        $response = $this->call('POST', '/internal/v1/tenants', [], [], [], [], '{}');

        $response->assertStatus(400);
        $this->assertStringContainsString('VALIDATION_ERROR', $response->getContent());
    }

    public function test_reject_invalid_signature(): void
    {
        $rawBody = '{"tenant_uuid":"tenant-invalid-signature"}';

        $headers = [
            InternalHeaders::KEY_ID => $this->keyId,
            InternalHeaders::TIMESTAMP => (string) time(),
            InternalHeaders::SIGNATURE => 'invalid-signature',
        ];

        $response = $this->callRaw('POST', '/internal/v1/tenants', $rawBody, $headers);

        $response->assertStatus(401);
        $this->assertStringContainsString('INVALID_SIGNATURE', $response->getContent());
    }

    public function test_accept_valid_signature_calls_adapter(): void
    {
        FakeTenantAdapter::$lastCreateTenantRequest = null;

        $tenantUuid = 'tenant-valid-signature';
        $rawBody = sprintf('{"tenant_uuid":"%s"}', $tenantUuid);
        $headers = $this->signedHeaders('POST', '/internal/v1/tenants', $rawBody, (string) time(), 'nonce-ok-1');

        $response = $this->callRaw('POST', '/internal/v1/tenants', $rawBody, $headers);

        $response->assertStatus(200);
        $response->assertJsonPath('ok', true);

        self::assertNotNull(FakeTenantAdapter::$lastCreateTenantRequest);

        $receivedTenantUuid = $this->extractTenantUuid(FakeTenantAdapter::$lastCreateTenantRequest);
        self::assertSame($tenantUuid, $receivedTenantUuid);
    }

    public function test_nonce_replay_fails_when_enabled(): void
    {
        config()->set('sipro-internal-api-laravel.hmac.nonce.enabled', true);

        $rawBody = '{"tenant_uuid":"tenant-nonce-replay"}';
        $timestamp = (string) time();
        $nonce = 'same-nonce';

        $headers = $this->signedHeaders('POST', '/internal/v1/tenants', $rawBody, $timestamp, $nonce);

        $first = $this->callRaw('POST', '/internal/v1/tenants', $rawBody, $headers);
        $second = $this->callRaw('POST', '/internal/v1/tenants', $rawBody, $headers);

        $first->assertStatus(200);
        $second->assertStatus(401);
        $this->assertStringContainsString('NONCE_REPLAY', $second->getContent());
    }

    /**
     * @param array<string,string> $headers
     */
    private function callRaw(string $method, string $uri, string $rawBody, array $headers)
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        foreach ($headers as $header => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $header))] = $value;
        }

        return $this->call($method, $uri, [], [], [], $server, $rawBody);
    }

    /**
     * @return array<string,string>
     */
    private function signedHeaders(
        string $method,
        string $path,
        string $rawBody,
        string $timestamp,
        string $nonce
    ): array {
        /** @var HmacSigner $signer */
        $signer = $this->app->make(HmacSigner::class);

        $headers = [
            InternalHeaders::KEY_ID => $this->keyId,
            InternalHeaders::TIMESTAMP => $timestamp,
            InternalHeaders::NONCE => $nonce,
        ];

        $parameters = (new ReflectionMethod($signer, 'sign'))->getParameters();
        $args = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            $args[] = match ($name) {
                'method', 'httpMethod' => $method,
                'path', 'uriPath' => $path,
                'headers', 'headerMap' => $headers,
                'body', 'rawBody' => $rawBody,
                'keyId' => $this->keyId,
                'secret', 'secretKey', 'keySecret' => $this->secret,
                'timestamp' => $timestamp,
                'nonce' => $nonce,
                default => $parameter->isDefaultValueAvailable()
                    ? $parameter->getDefaultValue()
                    : throw new RuntimeException('Unsupported HmacSigner::sign argument: ' . $name),
            };
        }

        $result = $signer->sign(...$args);

        if (is_string($result)) {
            return $headers + [InternalHeaders::SIGNATURE => $result];
        }

        if (is_array($result)) {
            $normalized = [];
            foreach ($result as $key => $value) {
                if (is_string($key) && is_scalar($value)) {
                    $normalized[$key] = (string) $value;
                }
            }

            if (isset($normalized[InternalHeaders::SIGNATURE])) {
                return $normalized;
            }

            if (isset($normalized['signature'])) {
                return $headers + [InternalHeaders::SIGNATURE => $normalized['signature']];
            }
        }

        throw new RuntimeException('Unable to obtain signature from HmacSigner.');
    }

    private function extractTenantUuid(object $dto): ?string
    {
        if (property_exists($dto, 'tenant_uuid') && is_string($dto->tenant_uuid)) {
            return $dto->tenant_uuid;
        }

        if (property_exists($dto, 'tenantUuid') && is_string($dto->tenantUuid)) {
            return $dto->tenantUuid;
        }

        if (method_exists($dto, 'toArray')) {
            $data = $dto->toArray();
            if (is_array($data) && isset($data['tenant_uuid']) && is_string($data['tenant_uuid'])) {
                return $data['tenant_uuid'];
            }
        }

        return null;
    }
}
