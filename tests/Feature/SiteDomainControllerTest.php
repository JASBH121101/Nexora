<?php

namespace Tests\Feature;

use App\Core\Site\Domains\DnsRecordResolver;
use App\Core\Site\Domains\TlsCertificateInspector;
use App\Models\InstallationSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteDomainControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_register_a_domain_and_receives_a_txt_ownership_token(): void
    {
        $this->installOwner();

        $this->post(route('admin.site-domains.store'), [
            'hostname' => ' WWW.Example.com. ',
            'type' => 'custom_domain',
        ])->assertRedirect(route('admin.site-domains.index'));

        $domain = data_get(
            InstallationSettings::query()->findOrFail(1)->configuration,
            'site.domains.0',
        );

        $this->assertSame('www.example.com', $domain['hostname']);
        $this->assertSame('custom_domain', $domain['type']);
        $this->assertSame('_nexora-verification.www.example.com', $domain['verification']['txt_record_name']);
        $this->assertSame(40, strlen($domain['verification']['token']));
        $this->assertSame('pending', $domain['status']);
    }

    public function test_invalid_hostnames_are_rejected(): void
    {
        $this->installOwner();

        $this->from(route('admin.site-domains.index'))
            ->post(route('admin.site-domains.store'), [
                'hostname' => 'https://example.com/path',
                'type' => 'custom_domain',
            ])
            ->assertRedirect(route('admin.site-domains.index'))
            ->assertSessionHasErrors('hostname');

        $this->assertSame(
            [],
            (array) data_get(InstallationSettings::query()->findOrFail(1)->configuration, 'site.domains', []),
        );
    }

    public function test_unverified_domain_cannot_be_selected_as_primary(): void
    {
        $this->installOwner();
        $this->post(route('admin.site-domains.store'), [
            'hostname' => 'shop.example.com',
            'type' => 'external_subdomain',
        ]);

        $domain = data_get(InstallationSettings::query()->findOrFail(1)->configuration, 'site.domains.0');

        $this->from(route('admin.site-domains.index'))
            ->post(route('admin.site-domains.primary', $domain['id']))
            ->assertRedirect(route('admin.site-domains.index'))
            ->assertSessionHasErrors('domain');

        $this->assertNull(data_get(
            InstallationSettings::query()->findOrFail(1)->configuration,
            'site.primary_domain_id',
        ));
    }

    public function test_owner_can_verify_dns_txt_and_tls_then_select_primary_domain(): void
    {
        $this->installOwner();
        config(['nexora.site_dns_targets' => ['1.1.1.1']]);

        $dns = new class implements DnsRecordResolver
        {
            public array $txt = [];

            public function resolveAddresses(string $hostname): array
            {
                return ['1.1.1.1'];
            }

            public function resolveTxt(string $recordName): array
            {
                return $this->txt[$recordName] ?? [];
            }
        };
        $tls = new class implements TlsCertificateInspector
        {
            public function inspect(string $hostname, array $publicAddresses): array
            {
                return [
                    'status' => 'valid',
                    'checked_at' => now()->toIso8601String(),
                    'valid_from' => now()->subDay()->toIso8601String(),
                    'valid_until' => now()->addYear()->toIso8601String(),
                ];
            }
        };
        $this->app->instance(DnsRecordResolver::class, $dns);
        $this->app->instance(TlsCertificateInspector::class, $tls);

        $this->post(route('admin.site-domains.store'), [
            'hostname' => 'shop.example.com',
            'type' => 'custom_domain',
        ]);

        $domain = data_get(InstallationSettings::query()->findOrFail(1)->configuration, 'site.domains.0');
        $dns->txt[$domain['verification']['txt_record_name']] = [$domain['verification']['token']];

        $this->post(route('admin.site-domains.diagnose', $domain['id']))
            ->assertRedirect(route('admin.site-domains.index'));

        $verified = data_get(InstallationSettings::query()->findOrFail(1)->configuration, 'site.domains.0');
        $this->assertSame('ready', $verified['status']);
        $this->assertNotEmpty($verified['verified_at']);

        $this->post(route('admin.site-domains.primary', $domain['id']))
            ->assertRedirect(route('admin.site-domains.index'));

        $configuration = InstallationSettings::query()->findOrFail(1)->configuration;
        $this->assertSame($domain['id'], data_get($configuration, 'site.primary_domain_id'));
        $this->assertTrue(data_get($configuration, 'site.domains.0.is_primary'));
    }

    public function test_domain_management_is_not_available_to_employees(): void
    {
        $this->installOwner();

        $employee = User::query()->create([
            'name' => 'Empleado de prueba',
            'username' => 'employee-domain-test',
            'email' => 'employee-domain-test@example.com',
            'password' => 'Temporary-Password-123!',
            'role' => 'employee',
            'is_active' => true,
            'must_change_password' => false,
            'permissions' => [],
        ]);

        $this->actingAs($employee)
            ->get(route('admin.site-domains.index'))
            ->assertForbidden();
    }

    public function test_removing_primary_domain_clears_primary_selection(): void
    {
        $this->installOwner();
        config(['nexora.site_dns_targets' => ['1.1.1.1']]);

        $dns = new class implements DnsRecordResolver
        {
            public array $txt = [];

            public function resolveAddresses(string $hostname): array
            {
                return ['1.1.1.1'];
            }

            public function resolveTxt(string $recordName): array
            {
                return $this->txt[$recordName] ?? [];
            }
        };
        $tls = new class implements TlsCertificateInspector
        {
            public function inspect(string $hostname, array $publicAddresses): array
            {
                return [
                    'status' => 'valid',
                    'checked_at' => now()->toIso8601String(),
                    'valid_from' => now()->subDay()->toIso8601String(),
                    'valid_until' => now()->addYear()->toIso8601String(),
                ];
            }
        };
        $this->app->instance(DnsRecordResolver::class, $dns);
        $this->app->instance(TlsCertificateInspector::class, $tls);

        $this->post(route('admin.site-domains.store'), [
            'hostname' => 'shop.example.com',
            'type' => 'custom_domain',
        ]);
        $domain = data_get(InstallationSettings::query()->findOrFail(1)->configuration, 'site.domains.0');
        $dns->txt[$domain['verification']['txt_record_name']] = [$domain['verification']['token']];
        $this->post(route('admin.site-domains.diagnose', $domain['id']));
        $this->post(route('admin.site-domains.primary', $domain['id']));

        $this->delete(route('admin.site-domains.destroy', $domain['id']))
            ->assertRedirect(route('admin.site-domains.index'));

        $configuration = InstallationSettings::query()->findOrFail(1)->configuration;
        $this->assertSame([], (array) data_get($configuration, 'site.domains', []));
        $this->assertNull(data_get($configuration, 'site.primary_domain_id'));
    }

    private function installOwner(): User
    {
        $this->post('/setup', [
            'country_code' => 'NI',
            'locale' => 'es',
            'business_name' => 'Tienda de prueba',
            'business_type' => 'automotive',
            'owner_name' => 'Propietario de prueba',
            'owner_username' => 'jaredadmin',
            'owner_email' => 'owner@example.com',
            'owner_password' => 'Strong-Password-2026!',
            'owner_password_confirmation' => 'Strong-Password-2026!',
            'description' => 'Catálogo de repuestos y vehículos.',
            'primary_color' => '#123456',
            'primary_currency' => 'USD',
            'additional_currencies' => ['NIO'],
            'phones' => [
                ['country_calling_code' => '+505', 'number' => '8888-1234', 'show_public' => '1'],
            ],
            'primary_phone' => '0',
            'photos_enabled' => '1',
        ])->assertRedirect(route('admin.home'));

        return User::query()->where('email', 'owner@example.com')->firstOrFail();
    }
}
