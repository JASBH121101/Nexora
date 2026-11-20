<?php

namespace Tests\Feature;

use App\Models\InstallationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishedDomainMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_host_cannot_access_public_catalogue_after_primary_domain_is_set(): void
    {
        $this->post('/setup', [
            'country_code' => 'NI',
            'locale' => 'es-419',
            'business_name' => 'Tienda de prueba',
            'business_type' => 'automotive',
            'owner_name' => 'Propietario de prueba',
            'owner_username' => 'domainowner',
            'owner_email' => 'domain-owner@example.com',
            'owner_password' => 'Strong-Password-2026!',
            'owner_password_confirmation' => 'Strong-Password-2026!',
            'description' => 'Catálogo de prueba.',
            'primary_color' => '#123456',
            'primary_currency' => 'USD',
            'additional_currencies' => ['NIO'],
            'phones' => [
                ['country_calling_code' => '+505', 'number' => '8888-1234', 'show_public' => '1'],
            ],
            'primary_phone' => '0',
            'photos_enabled' => '0',
        ])->assertRedirect(route('admin.home'));

        $settings = InstallationSettings::query()->findOrFail(1);
        $settings->forceFill([
            'configuration' => [
                'site' => [
                    'primary_domain_id' => 'primary-domain',
                    'domains' => [[
                        'id' => 'primary-domain',
                        'hostname' => 'shop.example.com',
                        'status' => 'ready',
                        'verified_at' => now()->toIso8601String(),
                    ]],
                ],
            ],
        ])->save();

        $this->withServerVariables([
            'HTTP_HOST' => 'unlisted.example.net',
            'SERVER_NAME' => 'unlisted.example.net',
            'HTTPS' => 'on',
        ])->get('/')
            ->assertNotFound();
    }
}
