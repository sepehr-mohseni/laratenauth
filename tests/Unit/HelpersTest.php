<?php

declare(strict_types=1);

namespace Sepehr_Mohseni\LaraTenAuth\Tests\Unit;

use Sepehr_Mohseni\LaraTenAuth\Facades\TenantAuth;
use Sepehr_Mohseni\LaraTenAuth\Models\Tenant;
use Sepehr_Mohseni\LaraTenAuth\Tests\TestCase;

class HelpersTest extends TestCase
{
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Helper Test Tenant',
            'slug' => 'helper-test',
            'subdomain' => 'helper',
            'is_active' => true,
            'settings' => ['theme' => 'dark', 'language' => 'en'],
        ]);
    }

    public function test_tenant_helper_returns_null_by_default(): void
    {
        $result = tenant();

        $this->assertNull($result);
    }

    public function test_tenant_helper_returns_tenant_when_set(): void
    {
        TenantAuth::setTenant($this->tenant);

        $result = tenant();

        $this->assertInstanceOf(Tenant::class, $result);
        $this->assertEquals($this->tenant->id, $result->id);
    }

    public function test_tenant_id_helper_returns_null_by_default(): void
    {
        $result = tenant_id();

        $this->assertNull($result);
    }

    public function test_tenant_id_helper_returns_id_when_tenant_set(): void
    {
        TenantAuth::setTenant($this->tenant);

        $result = tenant_id();

        $this->assertEquals($this->tenant->id, $result);
    }

    public function test_has_tenant_helper_returns_false_by_default(): void
    {
        $this->assertFalse(has_tenant());
    }

    public function test_has_tenant_helper_returns_true_when_tenant_set(): void
    {
        TenantAuth::setTenant($this->tenant);

        $this->assertTrue(has_tenant());
    }

    public function test_in_tenant_helper_executes_callback_in_context(): void
    {
        $result = in_tenant($this->tenant, function () {
            return tenant()?->slug;
        });

        $this->assertEquals('helper-test', $result);
    }

    public function test_in_tenant_helper_with_tenant_id(): void
    {
        $result = in_tenant($this->tenant->id, function () {
            return tenant()?->id;
        });

        $this->assertEquals($this->tenant->id, $result);
    }

    public function test_tenant_setting_helper_returns_default_without_tenant(): void
    {
        $result = tenant_setting('nonexistent', 'default-value');

        $this->assertEquals('default-value', $result);
    }

    public function test_tenant_setting_helper_returns_setting_value(): void
    {
        TenantAuth::setTenant($this->tenant);

        $result = tenant_setting('theme', 'light');

        $this->assertEquals('dark', $result);
    }

    public function test_tenant_setting_helper_returns_default_for_missing_key(): void
    {
        TenantAuth::setTenant($this->tenant);

        $result = tenant_setting('nonexistent', 'fallback');

        $this->assertEquals('fallback', $result);
    }

    public function test_tenant_meta_helper_returns_default_without_tenant(): void
    {
        $result = tenant_meta('key', 'default');

        $this->assertEquals('default', $result);
    }

    public function test_tenant_user_helper_returns_null_when_not_authenticated(): void
    {
        $result = tenant_user();

        $this->assertNull($result);
    }
}
