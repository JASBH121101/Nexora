<?php

namespace Tests\Feature;

use App\Core\Site\Domains\DnsRecordResolver;
use App\Core\Site\Domains\TlsCertificateInspector;
use App\Models\InstallationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteDomainHostMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_domain_redirects_http_requests_to_https(): void
    {
        $this->createVerifiedPrimaryDomain('shop.example.com');

        $this->withServerVariables([
            'HTTP_HOST' => 'shop.example.com',
            'SERVER_NAME' => 'shop.example.com',
            'HTTPS' => 'off',
        ])->get('/')
            ->assertRedirect('https://shop.example.com/');
    }

    public function test_public_site_rejects_hosts_that_are_not_verified_for_the_installation(): void
    {
        $this->createVerifiedPrimaryDomain('shop.example.com');

        $this->withServerVariables([
            'HTTP_HOST' => 'unlisted.example.net',
            'SERVER_NAME' => 'unlisted.example.net',
            'HTTPS' => 'on',
        ])->get('/')
            ->assertNotFound();
    }

    private function createVerifiedPrimaryDomain(string $hostname): void
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

        config(['nexora.site_dns_targets' => ['1.1.1.1']]);

        $dns = new class implements DnsRecordResolver {
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
        $tls = new class implements TlsCertificateInspector {
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
            'hostname' => $hostname,
            'type' => 'custom_domain',
        ]);

        $domain = data_get(
            InstallationSettings::query()->findOrFail(1)->configuration,
            'site.domains.0',
        );
        $dns->txt[$domain['verification']['txt_record_name']] = [$domain['verification']['token']];

        $this->post(route('admin.site-domains.diagnose', $domain['id']));
        $this->post(route('admin.site-domains.primary', $domain['id']));
    }
}
