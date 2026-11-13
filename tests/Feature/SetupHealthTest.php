<?php

namespace Tests\Feature;

use App\Models\InstallationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_page_displays_server_preflight_checks(): void
    {
        $response = $this->get('/setup');

        $response->assertOk()
            ->assertSee('Preparación de instalación')
            ->assertSee('Versión de PHP')
            ->assertSee('Extensiones PHP')
            ->assertSee('Conexión a la base de datos')
            ->assertSee('Permisos de escritura');
    }

    public function test_public_site_redirects_to_setup_before_installation_is_completed(): void
    {
        $this->get('/')->assertRedirect(route('setup.show'));
    }

    public function test_completed_installation_displays_public_site_placeholder(): void
    {
        InstallationSettings::query()->create([
            'id' => 1,
            'business_name' => 'Tienda de prueba',
            'business_type' => 'other',
            'configuration' => [],
            'setup_completed_at' => now(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Tienda de prueba')
            ->assertSee('NEXORA Site Engine');
    }

    public function test_setup_page_redirects_after_installation_is_completed(): void
    {
        InstallationSettings::query()->create([
            'id' => 1,
            'business_name' => 'Tienda de prueba',
            'business_type' => 'other',
            'configuration' => [],
            'setup_completed_at' => now(),
        ]);

        $this->get('/setup')->assertRedirect(route('site.home'));
    }
}
