<?php

namespace Tests\Feature;

use App\Models\CatalogCategory;
use App\Models\CatalogGroup;
use App\Models\InstallationSettings;
use App\Models\LoginHelpRequest;
use App\Models\Product;
use App\Models\ProductFieldDefinition;
use App\Models\ProductFieldValue;
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

    public function test_setup_page_previews_every_available_template_from_the_template_catalog(): void
    {
        $response = $this->get('/setup');

        $response->assertOk()
            ->assertSee('const templatePreviews =', false)
            ->assertSee('Plantilla seleccionada:', false)
            ->assertSee('Perfumes', false)
            ->assertSee('Body mist', false)
            ->assertSee('Sets', false)
            ->assertDontSee('Cuidado personal', false)
            ->assertDontSee('Sets y estuches', false)
            ->assertSee('Calzado', false)
            ->assertSee('Computadoras', false)
            ->assertSee('Memoria RAM', false)
            ->assertSee('Ropa deportiva', false)
            ->assertSee('Cilindraje', false)
            ->assertSee('Abarrotes', false)
            ->assertSee('"other"', false);
    }

    public function test_setup_suggests_language_and_country_from_browser_preferences(): void
    {
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/setup')
            ->assertOk()
            ->assertSee('value="en" selected', false)
            ->assertSee('data-selected-country="US"', false)
            ->assertSee('const countryDialingCodes =', false)
            ->assertSee('"NI":"+505"', false)
            ->assertSee('Código de país', false)
            ->assertSee('Número móvil', false)
            ->assertSee('country_code', false)
            ->assertSee('const countryCodes =', false);
    }

    public function test_cancelling_setup_does_not_create_any_business_records(): void
    {
        $this->post('/setup/cancel')
            ->assertRedirect(route('setup.show'))
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'No se creó el propietario'));

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('installation_settings', 0);
        $this->assertDatabaseCount('business_contacts', 0);
        $this->assertDatabaseCount('business_currencies', 0);
        $this->assertDatabaseCount('catalog_categories', 0);
        $this->assertDatabaseCount('catalog_groups', 0);
    }

    public function test_setup_normalizes_split_phone_fields_to_international_e164_format(): void
    {
        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.home'));

        $contact = \\App\\Models\\BusinessContact::query()->firstOrFail();

        $this->assertSame('+50588881234', $contact->phone);
        $this->assertSame('+505 8888-1234', $contact->formatted_phone);
    }

    public function test_owner_can_override_the_suggested_calling_code_for_a_contact(): void
    {
        $payload = $this->validSetupPayload();
        $payload['phones'] = [
            ['country_calling_code' => '+1', 'number' => '202 555 0123', 'show_public' => '1'],
        ];

        $this->post('/setup', $payload)->assertRedirect(route('admin.home'));

        $contact = \\App\\Models\\BusinessContact::query()->firstOrFail();

        $this->assertSame('+12025550123', $contact->phone);
        $this->assertSame('+1 202 555 0123', $contact->formatted_phone);
    }

    public function test_setup_suggests_local_primary_currency_for_the_detected_country(): void
    {
        $this->withHeader('Accept-Language', 'es-NI,es;q=0.9')
            ->get('/setup')
            ->assertOk()
            ->assertSee('data-selected-country="NI"', false)
            ->assertSee('data-selected-currency="NIO"', false)
            ->assertSee('NIO', false)
            ->assertSee('"NI":"NIO"', false)
            ->assertSee('"GH":"GHS"', false);
    }

    public function test_setup_persists_primary_and_additional_currencies_and_business_hours(): void
    {
        $payload = $this->validSetupPayload();
        $payload['country_code'] = 'NI';
        $payload['primary_currency'] = 'NIO';
        $payload['additional_currencies'] = ['USD', 'EUR'];
        $payload['business_hours'] = [
            ['day' => 'monday', 'open' => '08:00', 'close' => '17:00'],
            ['day' => 'saturday', 'open' => '09:00', 'close' => '13:00'],
        ];

        $this->post('/setup', $payload)->assertRedirect(route('admin.home'));

        $settings = InstallationSettings::query()->findOrFail(1);

        $this->assertSame('NIO', data_get($settings->configuration, 'pricing.primaryCurrency'));
        $this->assertSame(['NIO', 'USD', 'EUR'], data_get($settings->configuration, 'pricing.enabledCurrencies'));
        $this->assertSame($payload['business_hours'], data_get($settings->configuration, 'operation.hours'));
    }

    public function test_setup_persists_selected_country_and_language_with_installation_settings(): void
    {
        $payload = $this->validSetupPayload();
        $payload['country_code'] = 'US';
        $payload['locale'] = 'en';

        $this->post('/setup', $payload)->assertRedirect(route('admin.home'));

        $settings = InstallationSettings::query()->findOrFail(1);

        $this->assertSame('US', data_get($settings->configuration, 'regional.countryCode'));
        $this->assertSame('en', data_get($settings->configuration, 'regional.locale'));
    }

    public function test_perfumery_template_seeds_only_perfumes_body_mist_and_sets(): void
    {
        $payload = $this->validSetupPayload();
        $payload['business_type'] = 'perfumery';

        $this->post('/setup', $payload)->assertRedirect(route('admin.home'));

        $this->assertDatabaseCount('catalog_categories', 3);
        $this->assertDatabaseHas('catalog_categories', ['name' => 'Perfumes']);
        $this->assertDatabaseHas('catalog_categories', ['name' => 'Body mist']);
        $this->assertDatabaseHas('catalog_categories', ['name' => 'Sets']);
        $this->assertDatabaseMissing('catalog_categories', ['name' => 'Cuidado personal']);
        $this->assertDatabaseMissing('catalog_categories', ['name' => 'Sets y estuches']);

        $concentration = ProductFieldDefinition::query()
            ->where('field_key', 'concentration')
            ->firstOrFail();

        $this->assertSame(['EDT', 'EDP', 'Parfum'], $concentration->options);
    }

    public function test_public_site_redirects_to_setup_before_installation_is_completed(): void
    {
        $this->get('/')->assertRedirect(route('setup.show'));
    }

    public function test_setup_view_renders_its_pushed_styles_and_scripts(): void
    {
        $response = $this->get('/setup');

        $response->assertOk()
            ->assertSee('.field-grid', false)
            ->assertSee('@media(max-width:560px)', false)
            ->assertSee('const showStep =', false)
            ->assertSee('minlength="8"', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('aria-controls="owner-password"', false)
            ->assertSee('aria-controls="owner-password-confirmation"', false);
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
            ->assertSee('CATÁLOGO DIGITAL')
            ->assertDontSee('Versión de PHP')
            ->assertDontSee('Extensiones PHP')
            ->assertDontSee('Conexión a la base de datos')
            ->assertDontSee('Permisos de escritura');
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

    public function test_setup_accepts_an_owner_password_with_exactly_eight_characters(): void
    {
        $payload = $this->validSetupPayload();
        $payload['owner_password'] = 'Prueba89';
        $payload['owner_password_confirmation'] = 'Prueba89';

        $this->post('/setup', $payload)
            ->assertRedirect(route('admin.home'));

        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('Prueba89', $owner->password));
    }

    public function test_setup_rejects_an_owner_password_shorter_than_eight_characters(): void
    {
        $payload = $this->validSetupPayload();
        $payload['owner_password'] = 'Prueba8';
        $payload['owner_password_confirmation'] = 'Prueba8';

        $this->from('/setup')
            ->post('/setup', $payload)
            ->assertRedirect('/setup')
            ->assertSessionHasErrors('owner_password');

        $this->assertDatabaseCount('users', 0);
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

    public function test_owner_can_create_and_update_products_with_dynamic_fields_and_manual_prices(): void
    {
        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.home'));

        $category = CatalogCategory::query()->where('name', 'Vehículos')->firstOrFail();

        $response = $this->post(route('admin.products.store'), [
            'name' => 'Honda Civic',
            'category_id' => $category->id,
            'description' => 'Sedán de prueba.',
            'status' => 'published',
            'is_featured' => '1',
            'prices' => [
                'USD' => '10000.00',
                'NIO' => '',
            ],
            'fields' => [
                'brand' => 'Honda',
                'model' => 'Civic',
                'year' => '2022',
                'engine_displacement' => '2000',
                'compatibility' => 'Civic 2022',
            ],
        ]);

        $response->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('status');

        $product = Product::query()
            ->with(['fieldValues.definition', 'prices'])
            ->where('name', 'Honda Civic')
            ->firstOrFail();

        $this->assertSame('published', $product->status);
        $this->assertSame('honda-civic', $product->slug);
        $this->assertSame($category->id, $product->category_id);
        $this->assertCount(1, $product->prices);
        $this->assertSame('USD', $product->prices->first()->currency_code);
        $this->assertSame(10000.0, (float) $product->prices->first()->amount);

        $fieldValues = [];

        foreach ($product->fieldValues as $fieldValue) {
            $fieldValues[$fieldValue->definition->field_key] = $fieldValue->value['value'];
        }

        $this->assertSame('Honda', $fieldValues['brand']);
        $this->assertSame('2000', $fieldValues['engine_displacement']);

        $this->put(route('admin.products.update', $product), [
            'name' => 'Honda Civic Sport',
            'category_id' => $category->id,
            'description' => 'Versión actualizada.',
            'status' => 'draft',
            'prices' => [
                'USD' => '9500.00',
                'NIO' => '',
            ],
            'fields' => [
                'brand' => 'Honda',
                'model' => 'Civic Sport',
                'year' => '2023',
                'engine_displacement' => '2000',
                'compatibility' => 'Civic 2023',
            ],
        ])->assertRedirect(route('admin.products.index'));

        $updatedProduct = $product->fresh()->load('prices');

        $this->assertSame('Honda Civic Sport', $updatedProduct->name);
        $this->assertSame('honda-civic-sport', $updatedProduct->slug);
        $this->assertSame('draft', $updatedProduct->status);
        $this->assertSame(9500.0, (float) $updatedProduct->prices->first()->amount);
    }

    public function test_employee_product_access_obeys_catalog_permissions(): void
    {
        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.home'));

        $this->post('/admin/employees', [
            'name' => 'Empleado de prueba',
            'username' => 'empleado01',
            'email' => 'employee@example.com',
        ])->assertRedirect();

        $employee = User::query()->where('username', 'empleado01')->firstOrFail();
        $employee->forceFill([
            'is_active' => true,
            'must_change_password' => false,
            'permissions' => ['catalog.view'],
        ])->save();

        $this->actingAs($employee)->get('/admin/products')->assertOk();
        $this->get('/admin/products/create')->assertForbidden();

        $employee->forceFill(['permissions' => ['catalog.manage']])->save();
        $this->actingAs($employee->fresh())->get('/admin/products')->assertOk();
        $this->get('/admin/products/create')->assertOk();
    }

    public function test_public_catalog_only_lists_published_products_and_filters_by_category(): void
    {
        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.home'));

        $category = CatalogCategory::query()->where('name', 'Vehículos')->firstOrFail();

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Vehículo publicado',
            'slug' => 'vehiculo-publicado',
            'description' => 'Descripción visible.',
            'status' => 'published',
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Producto en borrador',
            'slug' => 'producto-en-borrador',
            'description' => 'No debe aparecer.',
            'status' => 'draft',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Vehículo publicado')
            ->assertDontSee('Producto en borrador');

        $this->get('/?category=vehiculos')
            ->assertOk()
            ->assertSee('Vehículo publicado');

        $this->get('/?q=No-existe')
            ->assertOk()
            ->assertSee('No encontramos productos');
    }

    public function test_public_product_detail_hides_private_dynamic_fields_and_drafts(): void
    {
        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.home'));

        $category = CatalogCategory::query()->where('name', 'Vehículos')->firstOrFail();
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Vehículo de muestra',
            'slug' => 'vehiculo-de-muestra',
            'description' => 'Detalle público.',
            'status' => 'published',
        ]);

        $privateBrand = ProductFieldDefinition::query()->where('field_key', 'brand')->firstOrFail();
        $privateBrand->forceFill(['is_public' => false])->save();

        $publicModel = ProductFieldDefinition::query()->where('field_key', 'model')->firstOrFail();

        ProductFieldValue::query()->create([
            'product_id' => $product->id,
            'field_definition_id' => $privateBrand->id,
            'value' => ['value' => 'INTERNAL-SECRET-BRAND'],
        ]);

        ProductFieldValue::query()->create([
            'product_id' => $product->id,
            'field_definition_id' => $publicModel->id,
            'value' => ['value' => 'Civic 2023'],
        ]);

        $this->get('/producto/vehiculo-de-muestra')
            ->assertOk()
            ->assertSee('Vehículo de muestra')
            ->assertSee('Civic 2023')
            ->assertDontSee('INTERNAL-SECRET-BRAND');

        $draft = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Producto reservado',
            'slug' => 'producto-reservado',
            'description' => 'No público.',
            'status' => 'draft',
        ]);

        $this->get('/producto/'.$draft->slug)->assertNotFound();
    }

    public function test_owner_can_create_update_and_deactivate_categories(): void
    {
        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.home'));

        $this->post(route('admin.categories.store'), [
            'name' => 'Accesorios de moto',
        ])->assertRedirect()->assertSessionHas('status');

        $category = CatalogCategory::query()
            ->where('name', 'Accesorios de moto')
            ->firstOrFail();

        $this->assertSame('accesorios-de-moto', $category->slug);
        $this->assertTrue($category->is_active);

        $this->put(route('admin.categories.update', $category), [
            'name' => 'Accesorios para motocicleta',
        ])->assertRedirect();

        $category->refresh();

        $this->assertSame('accesorios-para-motocicleta', $category->slug);

        $this->post(route('admin.categories.status', $category), [
            'is_active' => '0',
        ])->assertRedirect();

        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_owner_can_create_and_configure_dynamic_fields(): void
    {
        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.home'));

        $group = CatalogGroup::query()->firstOrFail();

        $this->post(route('admin.fields.store'), [
            'group_id' => $group->id,
            'field_key' => 'product_condition',
            'label' => 'Condición',
            'field_type' => 'select',
            'options_text' => "Nuevo\nUsado\nReacondicionado",
            'width' => 'half',
            'is_public' => '1',
            'is_searchable' => '1',
        ])->assertRedirect()->assertSessionHas('status');

        $field = ProductFieldDefinition::query()
            ->where('field_key', 'product_condition')
            ->firstOrFail();

        $this->assertSame(['Nuevo', 'Usado', 'Reacondicionado'], $field->options);
        $this->assertTrue($field->is_public);
        $this->assertTrue($field->is_searchable);

        $this->put(route('admin.fields.update', $field), [
            'group_id' => $group->id,
            'field_key' => 'condition_status',
            'label' => 'Estado del producto',
            'field_type' => 'select',
            'options_text' => "Nuevo\nUsado",
            'width' => 'full',
            'is_required' => '1',
            'unit_dimension' => 'condition',
            'unit_code' => '',
        ])->assertRedirect();

        $field->refresh();

        $this->assertSame('condition_status', $field->field_key);
        $this->assertSame('Estado del producto', $field->label);
        $this->assertSame(['Nuevo', 'Usado'], $field->options);
        $this->assertTrue($field->is_required);
        $this->assertFalse($field->is_public);
        $this->assertFalse($field->is_searchable);
        $this->assertSame('full', $field->width);
    }

    public function test_employee_catalog_configuration_requires_the_specific_permission(): void
    {
        $this->post('/setup', $this->validSetupPayload())->assertRedirect(route('admin.home'));

        $this->post('/admin/employees', [
            'name' => 'Empleado de prueba',
            'username' => 'empleado01',
            'email' => 'employee@example.com',
        ])->assertRedirect();

        $employee = User::query()->where('username', 'empleado01')->firstOrFail();
        $employee->forceFill([
            'is_active' => true,
            'must_change_password' => false,
            'permissions' => ['categories.manage'],
        ])->save();

        $this->actingAs($employee)->get('/admin/categories')->assertOk();
        $this->get('/admin/fields')->assertForbidden();

        $employee->forceFill(['permissions' => ['fields.manage']])->save();

        $this->actingAs($employee->fresh())->get('/admin/fields')->assertOk();
        $this->get('/admin/categories')->assertForbidden();
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
        ];
    }
}
