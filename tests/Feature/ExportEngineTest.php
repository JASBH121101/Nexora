<?php

namespace Tests\Feature;

use App\Models\CatalogCategory;
use App\Models\ExportHistory;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExportEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_export_csv_and_the_export_is_recorded(): void
    {
        $this->completeInstallation();

        $category = CatalogCategory::query()->where('name', 'Vehículos')->firstOrFail();
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => '=1+1',
            'slug' => 'formula-injection-test',
            'description' => 'Descripción de prueba.',
            'status' => 'published',
            'quantity_available' => '3.500',
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'currency_code' => 'USD',
            'amount' => '12500.00',
        ]);

        $response = $this->post(route('admin.exports.store'), [
            'format' => 'csv',
            'status' => 'published',
            'include_prices' => '1',
            'include_inventory' => '1',
        ]);

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        self::assertStringContainsString("'=1+1", $response->getContent());
        self::assertStringContainsString('12500.0000', $response->getContent());
        self::assertStringContainsString('3.500', $response->getContent());

        $this->assertDatabaseHas('export_histories', [
            'user_id' => auth()->id(),
            'format' => 'csv',
            'status' => 'completed',
            'record_count' => 1,
        ]);
    }

    public function test_export_employee_cannot_include_prices_when_the_role_denies_price_visibility(): void
    {
        $this->completeInstallation();

        $role = Role::query()->create([
            'name' => 'Exportador limitado',
            'slug' => 'limited-exporter',
            'permissions' => ['products.view', 'exports.create', 'exports.download'],
            'field_permissions' => [
                'products' => [
                    'price' => ['view' => 'deny', 'edit' => 'deny'],
                    'description' => ['view' => 'deny', 'edit' => 'deny'],
                ],
            ],
            'is_system' => false,
        ]);

        $employee = User::query()->create([
            'name' => 'Exportador limitado',
            'username' => 'limited-exporter',
            'email' => 'limited-exporter@example.com',
            'password' => 'Temporary-Password-123!',
            'role' => 'employee',
            'role_id' => $role->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($employee)
            ->post(route('admin.exports.store'), [
                'format' => 'json',
                'status' => 'published',
                'include_prices' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('export_histories', 0);
    }

    public function test_export_user_without_download_permission_is_denied(): void
    {
        $this->completeInstallation();

        $role = Role::query()->create([
            'name' => 'Solo crear exportaciones',
            'slug' => 'export-create-only',
            'permissions' => ['products.view', 'exports.create'],
            'field_permissions' => [],
            'is_system' => false,
        ]);

        $employee = User::query()->create([
            'name' => 'Usuario sin descarga',
            'username' => 'no-download',
            'email' => 'no-download@example.com',
            'password' => 'Temporary-Password-123!',
            'role' => 'employee',
            'role_id' => $role->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($employee)
            ->post(route('admin.exports.store'), [
                'format' => 'json',
                'status' => 'published',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('export_histories', 0);
    }

    private function completeInstallation(): void
    {
        $this->post('/setup', [
            'country_code' => 'NI',
            'locale' => 'es-419',
            'business_name' => 'Catálogo de exportación',
            'business_type' => 'automotive',
            'owner_name' => 'Propietario de prueba',
            'owner_username' => 'export-owner',
            'owner_email' => 'export-owner@example.com',
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
    }
}
