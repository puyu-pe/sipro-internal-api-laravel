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

    public function test_lifecycle_routes_are_loaded(): void
    {
        $warn = $this->call('POST', '/internal/v1/tenants/appkey:warn', [], [], [], [], '{}');
        $suspend = $this->call('POST', '/internal/v1/tenants/appkey:suspend', [], [], [], [], '{}');
        $activate = $this->call('POST', '/internal/v1/tenants/appkey:activate', [], [], [], [], '{}');

        $this->assertNotSame(404, $warn->getStatusCode());
        $this->assertNotSame(404, $suspend->getStatusCode());
        $this->assertNotSame(404, $activate->getStatusCode());
    }

    public function test_closure_routes_are_loaded(): void
    {
        $close = $this->call('POST', '/internal/v1/tenants/appkey:close', [], [], [], [], '{}');
        $reopen = $this->call('POST', '/internal/v1/tenants/appkey:reopen', [], [], [], [], '{}');

        $this->assertNotSame(404, $close->getStatusCode());
        $this->assertNotSame(404, $reopen->getStatusCode());
    }

    public function test_clone_routes_are_loaded(): void
    {
        $export = $this->call('POST', '/internal/v1/tenants/appkey:export', [], [], [], [], '{}');
        $import = $this->call('POST', '/internal/v1/tenants/appkey:import', [], [], [], [], '{}');

        $this->assertNotSame(404, $export->getStatusCode());
        $this->assertNotSame(404, $import->getStatusCode());
    }

    public function test_impersonate_route_is_loaded(): void
    {
        $response = $this->call('POST', '/internal/v1/tenants/appkey:impersonate', [], [], [], [], '{}');

        $this->assertNotSame(404, $response->getStatusCode());
    }

    public function test_impersonable_user_search_route_is_loaded(): void
    {
        $response = $this->call('POST', '/internal/v1/tenants/appkey:impersonation-users/search', [], [], [], [], '{}');

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

        $appKey = 'appkey-11111111-1111-4111-8111-111111111111';
        $rawBody = $this->validCreateTenantRawBody($appKey);
        $headers = $this->signedHeaders('POST', '/internal/v1/tenants', $rawBody, (string) time(), 'nonce-ok-1');

        $response = $this->callRaw('POST', '/internal/v1/tenants', $rawBody, $headers);

        $response->assertStatus(200);
        $response->assertJsonPath('ok', true);

        self::assertNotNull(FakeTenantAdapter::$lastCreateTenantRequest);

        $receivedAppKey = $this->extractAppKey(FakeTenantAdapter::$lastCreateTenantRequest);
        self::assertSame($appKey, $receivedAppKey);
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

    public function test_search_endpoint_returns_validation_error_for_invalid_pagination(): void
    {
        $rawBody = json_encode([
            'resolveKey' => 'appkey-11111111-1111-4111-8111-111111111111',
            'projectCode' => 'pbuds00047',
            'page' => 0,
            'perPage' => 51,
        ]);
        $headers = $this->signedHeaders(
            'POST',
            '/internal/v1/tenants/appkey:impersonation-users/search',
            $rawBody,
            (string) time(),
            'nonce-search-validation'
        );

        $response = $this->callRaw('POST', '/internal/v1/tenants/appkey:impersonation-users/search', $rawBody, $headers);

        $response->assertStatus(400);
        $response->assertJsonPath('code', 'VALIDATION_ERROR');
        $response->assertJsonPath('details.errors.0.field', 'page');
    }

    public function test_search_endpoint_returns_paginated_user_payload(): void
    {
        FakeTenantAdapter::$lastImpersonableUserSearchRequest = null;

        $rawBody = json_encode([
            'resolveKey' => 'appkey-11111111-1111-4111-8111-111111111111',
            'projectCode' => 'pbuds00047',
            'query' => 'juan',
            'page' => 1,
            'perPage' => 20,
        ]);
        $headers = $this->signedHeaders(
            'POST',
            '/internal/v1/tenants/appkey:impersonation-users/search',
            $rawBody,
            (string) time(),
            'nonce-search-ok'
        );

        $response = $this->callRaw('POST', '/internal/v1/tenants/appkey:impersonation-users/search', $rawBody, $headers);

        $response->assertStatus(200);
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('users.0.id', 42);
        $response->assertJsonPath('pagination.perPage', 20);
        self::assertSame('juan', FakeTenantAdapter::$lastImpersonableUserSearchRequest?->query);
    }

    public function test_impersonate_endpoint_validates_duration_policy(): void
    {
        $rawBody = json_encode([
            'resolveKey' => 'appkey-11111111-1111-4111-8111-111111111111',
            'projectCode' => 'pbuds00047',
            'targetUserId' => 42,
            'durationMinutes' => 61,
        ]);
        $headers = $this->signedHeaders(
            'POST',
            '/internal/v1/tenants/appkey:impersonate',
            $rawBody,
            (string) time(),
            'nonce-impersonate-validation'
        );

        $response = $this->callRaw('POST', '/internal/v1/tenants/appkey:impersonate', $rawBody, $headers);

        $response->assertStatus(400);
        $response->assertJsonPath('code', 'VALIDATION_ERROR');
        $response->assertJsonPath('details.errors.0.field', 'durationMinutes');
    }

    public function test_impersonate_endpoint_returns_effective_duration_metadata(): void
    {
        FakeTenantAdapter::$lastImpersonationRequest = null;

        $rawBody = json_encode([
            'resolveKey' => 'appkey-11111111-1111-4111-8111-111111111111',
            'projectCode' => 'pbuds00047',
            'targetUserId' => 42,
            'durationMinutes' => 30,
        ]);
        $headers = $this->signedHeaders(
            'POST',
            '/internal/v1/tenants/appkey:impersonate',
            $rawBody,
            (string) time(),
            'nonce-impersonate-ok'
        );

        $response = $this->callRaw('POST', '/internal/v1/tenants/appkey:impersonate', $rawBody, $headers);

        $response->assertStatus(200);
        $response->assertJsonPath('effectiveDurationMinutes', 30);
        self::assertSame(30, FakeTenantAdapter::$lastImpersonationRequest?->durationMinutes);
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


    private function validCreateTenantRawBody(string $resolveKey): string
    {
        return json_encode([
            'project' => [
                'name' => 'Proyecto Demo',
                'code' => 'pbuds00047',
                'description' => 'Proyecto Demo',
                'billingCycle' => 'monthly',
                'priceAgreed' => 120.5,
                'startDate' => '2026-03-01',
                'renewalDate' => null,
                'execStatus' => 'active',
                'isActive' => true,
                'accessUrlCustom' => null,
                'accessUrls' => [
                    'panel' => 'https://demo.local/panel',
                ],
                'resolveKey' => $resolveKey,
                'logo' => null,
                'address' => null,
                'phone' => null,
                'email' => null,
                'ubigeo' => null,
                'latitud' => null,
                'longitud' => null,
                'color' => null,
                'notes' => null,
            ],
            'client' => [
                'ruc' => '20123456789',
                'businessName' => 'Demo Transportes SAC',
                'tradeName' => 'Demo Transportes',
            ],
            'services' => [
                [
                    'key' => 'yubus',
                    'externalId' => null,
                    'code' => 'YUBUS',
                    'name' => 'Yubus',
                    'description' => 'Sistema de transporte',
                    'priceList' => 100.0,
                    'defaultBillingCycle' => 'monthly',
                    'type' => 'saas',
                    'accessUrl' => null,
                    'logo' => null,
                    'credentials' => [
                        [
                            'name' => 'Admin Test',
                            'username' => 'admin@example.com',
                            'email' => 'admin@example.com',
                            'role' => 'admin',
                            'initialPassword' => 'Temporal123!',
                            'mustChangePassword' => true,
                        ],
                    ],
                    'modules' => [
                        [
                            'id' => null,
                            'externalId' => null,
                            'name' => 'Core',
                            'description' => 'Modulo base',
                            'price' => 0,
                            'isUnlimited' => true,
                            'customPrice' => null,
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function extractResolveKey(object $dto): ?string
    {
        if (property_exists($dto, 'project') && $dto->project !== null) {
            if (property_exists($dto->project, 'resolveKey') && is_string($dto->project->resolveKey)) {
                return $dto->project->resolveKey;
            }
        }

        return null;
    }
}
