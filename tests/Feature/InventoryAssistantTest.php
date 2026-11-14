<?php

namespace Tests\Feature;

use App\Core\Authorization\RoleCatalog;
use App\Models\CatalogCategory;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InventoryAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_inventory_assistant_can_change_quantity_but_cannot_read_prices_or_descriptions(): void
    {
        $this->completeInstallation();

        RoleCatalog::ensureDefaults();
        $role = Role::query()->where('slug', 'inventory-assistant')->firstOrFail();

        $category = CatalogCategory::query()->where('name', 'Vehículos')->firstOrFail();
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Vehículo de inventario',
            'slug' => 'vehiculo-de-inventario',
            'description' => 'DESCRIPCION-PRIVADA-778',
            'status' => 'published',
            'quantity_available' => '4.000',
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'currency_code' => 'USD',
            'amount' => '12500.00',
        ]);

        $employee = User::query()->create([
            'name' => 'Auxiliar de inventario',
            'username' => 'inventory-assistant',
            'email' => 'inventory-assistant@example.com',
            'password' => 'Temporary-Password-123!',
            'role' => 'employee',
            'role_id' => $role->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($employee)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('name="quantity_available"', false)
            ->assertDontSee('12500.00')
            ->assertDontSee('DESCRIPCION-PRIVADA-778');

        $this->put(route('admin.products.update', $product), [
            'quantity_available' => '12.500',
        ])->assertRedirect(route('admin.products.index'));

        $product->refresh();
        self::assertSame('12.500', $product->quantity_available);
        self::assertSame('12500.0000', ProductPrice::query()
            ->where('product_id', $product->id)
            ->value('amount'));
        self::assertSame('DESCRIPCION-PRIVADA-778', $product->description);
    }

    private function completeInstallation(): void
    {
        $this->post('/setup', [
            'country_code' => 'NI',
            'locale' => 'es-419',
            'business_name' => 'Catálogo de inventario',
            'business_type' => 'automotive',
            'owner_name' => 'Propietario de prueba',
            'owner_username' => 'inventory-owner',
            'owner_email' => 'inventory-owner@example.com',
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
