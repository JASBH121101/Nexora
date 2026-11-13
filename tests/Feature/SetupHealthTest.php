<?php

namespace Tests\Feature;

use App\Models\InstallationSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetupHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_page_displays_server_preflight_checks(): void
    {
        $response = $this->get('/setup');

        $response->assertOk()
            ->assertSee('Prepara tu catálogo')
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

        $this->get('/setup')->assertRedirect(route('admin.login'));
    }

    public function test_setup_creates_one_owner_and_seeds_the_selected_template_atomically(): void
    {
        $response = $this->post('/setup', $this->validSetupPayload());

        $response->assertRedirect(route('admin.home'));
        $this->assertAuthenticated();

        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($owner);
        $this->assertTrue(Hash::check('Strong-Password-2026!', $owner->password));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('installation_settings', [
            'id' => 1,
            'business_name' => 'Tienda de prueba',
            'business_type' => 'automotive',
            'owner_id' => $owner->id,
        ]);

        $this->assertDatabaseCount('catalog_categories', 3);
        $this->assertDatabaseHas('product_field_definitions', [
            'field_key' => 'engine_displacement',
            'unit_dimension' => 'displacement',
            'unit_code' => 'cc',
        ]);
        $this->assertDatabaseHas('business_currencies', [
            'code' => 'USD',
            'is_primary' => 1,
            'is_enabled' => 1,
        ]);
        $this->assertDatabaseHas('business_currencies', [
            'code' => 'NIO',
            'is_primary' => 0,
            'is_enabled' => 1,
        ]);
        $this->assertDatabaseHas('business_contacts', [
            'phone' => '+50588881234',
            'is_primary' => 1,
            'show_public' => 1,
        ]);
    }

    public function test_second_setup_attempt_cannot_create_another_owner(): void
    {
        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.home'));
        auth()->logout();

        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.login'));

        $this->assertDatabaseCount('users', 1);
    }

    public function test_only_the_installed_owner_can_open_the_admin_panel(): void
    {
        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.home'));
        auth()->logout();

        $intruder = User::query()->create([
            'name' => 'Usuario ajeno',
            'email' => 'intruder@example.com',
            'password' => 'Another-Strong-Password-2026!',
        ]);

        $this->actingAs($intruder)->get('/admin')->assertForbidden();
    }

    public function test_login_help_request_is_available_without_authentication(): void
    {
        $response = $this->post('/admin/login/help', [
            'username' => 'empleado01',
            'reason' => 'forgot_password',
            'details' => 'No recuerdo la contraseña.',
        ]);

        $response->assertRedirect(route('admin.login'))
            ->assertSessionHas('status');
        $this->assertDatabaseHas('login_help_requests', [
            'username' => 'empleado01',
            'reason' => 'forgot_password',
            'status' => 'pending',
        ]);
    }

    public function test_owner_can_create_employee_with_temporary_password_required_to_change(): void
    {
        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.home'));

        $response = $this->post('/admin/employees', [
            'name' => 'Empleado de prueba',
            'username' => 'empleado01',
            'email' => 'employee@example.com',
        ]);

        $temporaryPassword = null;

        $response->assertRedirect()->assertSessionHas(
            'employee_credentials_once',
            function (array $credentials) use (&$temporaryPassword): bool {
                $temporaryPassword = $credentials['password'] ?? null;

                return filled($temporaryPassword);
            },
        );

        $employee = User::query()->where('username', 'empleado01')->firstOrFail();
        $this->assertSame('employee', $employee->role);
        $this->assertTrue($employee->must_change_password);
        $this->assertTrue(Hash::check($temporaryPassword, $employee->password));
    }

    public function test_owner_can_log_in_again_after_logout(): void
    {
        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.home'));
        $this->post('/admin/logout')->assertRedirect(route('admin.login'));

        $this->post('/admin/login', [
            'username' => 'jaredadmin',
            'password' => 'Strong-Password-2026!',
        ])->assertRedirect(route('admin.home'));

        $this->assertAuthenticatedAs(User::query()->where('email', 'owner@example.com')->firstOrFail());
    }

    private function validSetupPayload(): array
    {
        return [
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
                ['number' => '+50588881234', 'show_public' => '1'],
            ],
            'primary_phone' => '0',
            'photos_enabled' => '1',
        ];
    }
}
