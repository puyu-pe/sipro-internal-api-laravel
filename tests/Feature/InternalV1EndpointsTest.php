<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Tests\Feature;

use PuyuPe\SiproInternalApiCore\Http\InternalHeaders;
use PuyuPe\SiproInternalApiCore\Security\Hmac\HmacSigner;
use PuyuPe\SiproInternalApiLaravel\Tests\Fixtures\FakeTenantAdapter;
use PuyuPe\SiproInternalApiLaravel\Tests\TestCase;

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
            InternalHeaders::NONCE => 'invalid-signature-nonce',
            InternalHeaders::SIGNATURE => 'invalid-signature',
        ];

        $response = $this->callRaw('POST', '/internal/v1/tenants', $rawBody, $headers);

        $response->assertStatus(401);
        $this->assertStringContainsString('INVALID_SIGNATURE', $response->getContent());
    }

    public function test_accept_valid_signature_calls_adapter(): void
    {
        FakeTenantAdapter::$lastCreateTenantRequest = null;

        $tenantUuid = '11111111-1111-4111-8111-111111111111';
        $rawBody = $this->validCreateTenantRawBody($tenantUuid);
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

        $rawBody = $this->validCreateTenantRawBody('22222222-2222-4222-8222-222222222222');
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

        return $signer->buildSignedHeaders(
            $method,
            $path,
            $rawBody,
            $this->keyId,
            $this->secret,
            $timestamp,
            $nonce,
        );
    }


    private function validCreateTenantRawBody(string $tenantUuid): string
    {
        return json_encode([
            'tenant_uuid' => $tenantUuid,
            'tenant_name' => 'Tenant Test',
            'admin_user' => [
                'name' => 'Admin Test',
                'email' => 'admin@example.com',
                'temp_password' => 'Temporal123!',
            ],
            'locale_config' => [
                'timezone' => 'America/Lima',
                'currency' => 'PEN',
                'igv_rate' => 0.18,
            ],
        ]);
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
