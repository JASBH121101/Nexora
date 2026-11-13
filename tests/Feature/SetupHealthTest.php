<?php

namespace Tests\Feature;

use App\Models\InstallationSettings;
use App\Models\LoginHelpRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
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

    public function test_database_schema_can_be_prepared_from_the_setup_screen(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', ['--force' => true])
            ->andReturn(0);

        $this->post('/setup/prepare-database')
            ->assertRedirect(route('setup.show'))
            ->assertSessionHas('status');
    }

    public function test_database_preparation_is_blocked_after_installation_is_completed(): void
    {
        InstallationSettings::query()->create([
            'id' => 1,
            'business_name' => 'Tienda de prueba',
            'business_type' => 'other',
            'configuration' => [],
            'setup_completed_at' => now(),
        ]);

        Artisan::shouldReceive('call')->never();

        $this->post('/setup/prepare-database')
            ->assertRedirect(route('admin.login'));
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

        $this->post('/admin/logout')->assertRedirect(route('admin.login'));

        $this->post('/admin/login', [
            'username' => 'empleado01',
            'password' => $temporaryPassword,
        ])->assertRedirect(route('admin.home'));

        $this->get('/admin')->assertRedirect(route('admin.password.change'));

        $this->post('/admin/password/change', [
            'current_password' => $temporaryPassword,
            'password' => 'Employee-New-Password-2026!',
            'password_confirmation' => 'Employee-New-Password-2026!',
        ])->assertRedirect(route('admin.home'));

        $this->assertFalse($employee->fresh()->must_change_password);
        $this->get('/admin/employees')->assertForbidden();
        $this->get('/admin/access-requests')->assertForbidden();
    }

    public function test_owner_can_assign_only_known_permissions_to_an_employee(): void
    {
        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.home'));

        $this->post('/admin/employees', [
            'name' => 'Empleado de prueba',
            'username' => 'empleado01',
            'email' => 'employee@example.com',
        ])->assertRedirect();

        $employee = User::query()->where('username', 'empleado01')->firstOrFail();

        $this->post(route('admin.employees.permissions', $employee), [
            'permissions' => ['catalog.view', 'catalog.manage'],
        ])->assertRedirect();

        $employee = $employee->fresh();

        $this->assertSame(['catalog.view', 'catalog.manage'], $employee->permissions);
        $this->assertTrue($employee->hasPermission('catalog.view'));
        $this->assertTrue($employee->hasPermission('catalog.manage'));
        $this->assertFalse($employee->hasPermission('reports.view'));

        Route::middleware(['auth', 'permission:catalog.manage'])
            ->get('/__test/permission-check', static fn () => response('allowed'));

        $this->actingAs($employee)->get('/__test/permission-check')->assertOk();

        $owner = User::query()->where('username', 'jaredadmin')->firstOrFail();

        $this->actingAs($owner)->post(route('admin.employees.permissions', $employee), [
            'permissions' => ['owner.all'],
        ])->assertSessionHasErrors('permissions.0');

        $this->assertSame(['catalog.view', 'catalog.manage'], $employee->fresh()->permissions);
    }

    public function test_employee_cannot_assign_permissions_or_access_owner_controls(): void
    {
        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.home'));

        $this->post('/admin/employees', [
            'name' => 'Empleado de prueba',
            'username' => 'empleado01',
            'email' => 'employee@example.com',
        ])->assertRedirect();

        $employee = User::query()->where('username', 'empleado01')->firstOrFail();
        $employee->forceFill(['must_change_password' => false])->save();

        $this->actingAs($employee);

        $this->post(route('admin.employees.permissions', $employee), [
            'permissions' => ['catalog.manage'],
        ])->assertForbidden();

        $this->get('/admin/employees')->assertForbidden();
        $this->get('/admin/access-requests')->assertForbidden();
        $this->assertEmpty($employee->fresh()->permissions ?? []);
    }

    public function test_owner_can_process_employee_access_request_with_one_time_password(): void
    {
        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.home'));

        $this->post('/admin/employees', [
            'name' => 'Empleado de prueba',
            'username' => 'empleado01',
            'email' => 'employee@example.com',
        ])->assertRedirect();

        $employee = User::query()->where('username', 'empleado01')->firstOrFail();

        $this->post('/admin/logout')->assertRedirect(route('admin.login'));

        $this->post('/admin/login/help', [
            'username' => 'empleado01',
            'reason' => 'forgot_password',
            'details' => 'No recuerdo mi contraseña.',
        ])->assertRedirect(route('admin.login'));

        $this->post('/admin/login', [
            'username' => 'jaredadmin',
            'password' => 'Strong-Password-2026!',
        ])->assertRedirect(route('admin.home'));

        $request = LoginHelpRequest::query()->firstOrFail();
        $response = $this->post(route('admin.access-requests.temporary-password', $request));

        $temporaryPassword = null;

        $response->assertRedirect()->assertSessionHas(
            'temporary_password_once',
            function (array $credentials) use (&$temporaryPassword): bool {
                $temporaryPassword = $credentials['password'] ?? null;

                return filled($temporaryPassword);
            },
        );

        $this->assertTrue(Hash::check($temporaryPassword, $employee->fresh()->password));
        $this->assertTrue($employee->fresh()->must_change_password);
        $this->assertDatabaseHas('login_help_requests', [
            'id' => $request->id,
            'status' => 'resolved',
            'handled_by' => auth()->id(),
        ]);
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
