<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Tests\Feature;

use PuyuPe\SiproInternalApiLaravel\Tests\TestCase;

class InternalV1EndpointsTest extends TestCase
{
    public function test_create_tenant_endpoint_responds_ok_with_fake_adapter(): void
    {
        $response = $this->postJson('/internal/v1/tenants', []);

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('tenant_uuid', 'fake-tenant-uuid');
    }

    public function test_warn_tenant_endpoint_responds_ok_with_fake_adapter(): void
    {
        $response = $this->postJson('/internal/v1/tenants/tenant-1:warn', []);

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'warn')
            ->assertJsonPath('tenant_uuid', 'tenant-1');
    }

    public function test_suspend_tenant_endpoint_responds_ok_with_fake_adapter(): void
    {
        $response = $this->postJson('/internal/v1/tenants/tenant-1:suspend', []);

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'suspended')
            ->assertJsonPath('tenant_uuid', 'tenant-1');
    }

    public function test_activate_tenant_endpoint_responds_ok_with_fake_adapter(): void
    {
        $response = $this->postJson('/internal/v1/tenants/tenant-1:activate', []);

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('tenant_uuid', 'tenant-1');
    }
}
