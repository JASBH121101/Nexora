<?php

namespace Tests\Feature;

use App\Models\CatalogCategory;
use App\Models\Product;
use App\Models\ProductFieldDefinition;
use App\Models\ProductFieldValue;
use App\Models\ProductPrice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_inherits_module_permissions_and_field_restrictions_from_role(): void
    {
        $this->assertSame(
            'FIELD-SECRET-997',
            ProductFieldValue::query()
                ->where('product_id', $product->id)
                ->where('field_definition_id', $brand->id)
                ->firstOrFail()
                ->value['value'],
        );

        $role = Role::query()->create([
            'name' => 'Auxiliar de bodega',
            'slug' => 'auxiliar-bodega',
            'permissions' => [
                'products.view',
                'products.edit',
                'inventory.view',
                'inventory.edit_quantity',
            ],
            'field_permissions' => [
                'products' => [
                    'price' => ['view' => 'deny', 'edit' => 'deny'],
                ],
                'inventory' => [
                    'quantity' => ['view' => 'allow', 'edit' => 'deny'],
                ],
            ],
            'is_system' => false,
        ]);

        $employee = $this->createEmployee($role);

        $this->assertTrue($employee->hasPermission('products.view'));
        $this->assertTrue($employee->hasPermission('products.edit'));
        $this->assertFalse($employee->hasPermission('products.delete'));
        $this->assertTrue($employee->canAccessField('products', 'name', 'view'));
        $this->assertFalse($employee->canAccessField('products', 'price', 'view'));
        $this->assertFalse($employee->canAccessField('products', 'price', 'edit'));
        $this->assertTrue($employee->canAccessField('inventory', 'quantity', 'view'));
        $this->assertFalse($employee->canAccessField('inventory', 'quantity', 'edit'));
    }

    public function test_individual_permission_override_takes_precedence_over_role(): void
    {
        $role = Role::query()->create([
            'name' => 'Consulta',
            'slug' => 'consulta',
            'permissions' => ['products.view'],
            'field_permissions' => [],
            'is_system' => false,
        ]);

        $employee = $this->createEmployee($role, [
            'permissions' => [
                'products.view' => 'deny',
                'products.edit' => 'allow',
            ],
            'fields' => [
                'products' => [
                    'name' => ['edit' => 'deny'],
                ],
            ],
        ]);

        $this->assertFalse($employee->hasPermission('products.view'));
        $this->assertTrue($employee->hasPermission('products.edit'));
        $this->assertFalse($employee->canAccessField('products', 'name', 'edit'));
    }

    public function test_legacy_employee_permissions_remain_compatible(): void
    {
        $employee = User::query()->create([
            'name' => 'Empleado heredado',
            'username' => 'legacy-user',
            'email' => 'legacy@example.com',
            'password' => 'Temporary-Password-123!',
            'role' => 'employee',
            'is_active' => true,
            'must_change_password' => false,
            'permissions' => ['catalog.manage'],
        ]);

        $this->assertTrue($employee->hasPermission('products.view'));
        $this->assertTrue($employee->hasPermission('products.create'));
        $this->assertTrue($employee->hasPermission('products.edit'));
    }

    public function test_inactive_employee_and_unassigned_employee_are_denied_by_default(): void
    {
        $inactive = User::query()->create([
            'name' => 'Empleado inactivo',
            'username' => 'inactive-user',
            'email' => 'inactive@example.com',
            'password' => 'Temporary-Password-123!',
            'role' => 'employee',
            'is_active' => false,
            'must_change_password' => false,
        ]);

        $unassigned = User::query()->create([
            'name' => 'Empleado sin rol',
            'username' => 'unassigned-user',
            'email' => 'unassigned@example.com',
            'password' => 'Temporary-Password-123!',
            'role' => 'employee',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->assertFalse($inactive->hasPermission('products.view'));
        $this->assertFalse($unassigned->hasPermission('products.view'));
    }

    public function test_employee_cannot_read_or_edit_denied_product_fields_and_update_preserves_them(): void
    {
        $this->post('/setup', [
            'country_code' => 'NI',
            'locale' => 'es',
            'business_name' => 'Catálogo de prueba',
            'business_type' => 'automotive',
            'owner_name' => 'Propietario de prueba',
            'owner_username' => 'ownerpermission',
            'owner_email' => 'owner-permission@example.com',
            'owner_password' => 'Strong-Password-2026!',
            'owner_password_confirmation' => 'Strong-Password-2026!',
            'description' => 'Catálogo de ejemplo.',
            'primary_color' => '#123456',
            'primary_currency' => 'USD',
            'additional_currencies' => ['NIO'],
            'phones' => [
                ['country_calling_code' => '+505', 'number' => '8888-1234', 'show_public' => '1'],
            ],
            'primary_phone' => '0',
            'photos_enabled' => '1',
        ])->assertRedirect(route('admin.home'));

        $category = CatalogCategory::query()->where('name', 'Vehículos')->firstOrFail();
        $definitions = ProductFieldDefinition::query()
            ->availableForCategory($category->id)
            ->get();
        $brand = $definitions->firstWhere('field_key', 'brand');
        $model = $definitions->firstWhere('field_key', 'model');

        $this->assertNotNull($brand);
        $this->assertNotNull($model);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Vehículo con campos restringidos',
            'slug' => 'vehiculo-campos-restringidos',
            'description' => 'Ficha de prueba.',
            'status' => 'draft',
            'is_featured' => false,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'currency_code' => 'USD',
            'amount' => '12500.00',
        ]);

        foreach ($definitions as $definition) {
            $value = match ($definition->field_type) {
                'number' => '123',
                'select' => (string) (($definition->options ?? [])[0] ?? 'Opción'),
                'multiselect' => array_slice($definition->options ?? [], 0, 1),
                'boolean' => false,
                'date' => '2026-11-14',
                'textarea' => 'Texto de prueba.',
                default => 'Valor de '.$definition->field_key,
            };

            if ($definition->field_key === 'brand') {
                $value = 'FIELD-SECRET-997';
            }

            if ($definition->field_key === 'model') {
                $value = 'Civic';
            }

            ProductFieldValue::query()->create([
                'product_id' => $product->id,
                'field_definition_id' => $definition->id,
                'value' => ['value' => $value],
            ]);
        }

        $role = Role::query()->create([
            'name' => 'Auxiliar de bodega',
            'slug' => 'auxiliar-bodega-restricciones',
            'permissions' => ['products.view', 'products.edit'],
            'field_permissions' => [
                'products' => [
                    'price' => ['view' => 'deny', 'edit' => 'deny'],
                    'brand' => ['view' => 'deny', 'edit' => 'deny'],
                ],
            ],
            'is_system' => false,
        ]);

        $employee = $this->createEmployee($role);

        $this->actingAs($employee)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertDontSee('FIELD-SECRET-997')
            ->assertDontSee('12500.00');

        $submittedFields = [];

        foreach ($definitions as $definition) {
            if ($definition->field_key === 'brand') {
                continue;
            }

            $submittedFields[$definition->field_key] = match ($definition->field_type) {
                'number' => '456',
                'select' => (string) (($definition->options ?? [])[0] ?? 'Opción'),
                'multiselect' => array_slice($definition->options ?? [], 0, 1),
                'boolean' => '0',
                'date' => '2026-11-14',
                'textarea' => 'Texto actualizado.',
                default => 'Actualizado '.$definition->field_key,
            };
        }

        $submittedFields['model'] = 'Civic Sport';

        $this->put(route('admin.products.update', $product), [
            'name' => 'Vehículo actualizado',
            'category_id' => $category->id,
            'description' => 'Descripción actualizada.',
            'status' => 'draft',
            'is_featured' => '0',
            'fields' => $submittedFields,
        ])->assertRedirect(route('admin.products.index'));

        $this->assertSame(
            '12500.00',
            ProductPrice::query()->where('product_id', $product->id)->value('amount'),
        );

        $this->assertSame(
            'FIELD-SECRET-997',
            ProductFieldValue::query()
                ->where('product_id', $product->id)
                ->where('field_definition_id', $brand->id)
                ->firstOrFail()
                ->value['value'],
        );

        $this->assertSame(
            'Civic Sport',
            ProductFieldValue::query()
                ->where('product_id', $product->id)
                ->where('field_definition_id', $model->id)
                ->firstOrFail()
                ->value['value'],
        );
    }

    public function test_product_image_permissions_use_the_media_module(): void
    {
        $role = Role::query()->create([
            'name' => 'Editor de medios',
            'slug' => 'editor-medios',
            'permissions' => ['products.view', 'products.edit', 'media.view', 'media.replace'],
            'field_permissions' => [],
            'is_system' => false,
        ]);

        $employee = $this->createEmployee($role);

        $this->assertTrue($employee->canAccessField('products', 'image', 'view'));
        $this->assertTrue($employee->canAccessField('products', 'image', 'edit'));
        $this->assertFalse($employee->hasPermission('media.delete'));
    }

    private function createEmployee(Role $role, array $overrides = []): User
    {
        return User::query()->create([
            'name' => 'Empleado de prueba',
            'username' => 'employee-'.uniqid(),
            'email' => uniqid('employee-').'@example.com',
            'password' => 'Temporary-Password-123!',
            'role' => 'employee',
            'role_id' => $role->id,
            'is_active' => true,
            'must_change_password' => false,
            'permission_overrides' => $overrides,
        ]);
    }
}
