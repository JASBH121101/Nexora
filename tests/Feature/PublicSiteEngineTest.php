<?php

namespace Tests\Feature;

use App\Core\Library\Money\CurrencyCatalog;
use App\Models\CatalogCategory;
use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\ProductFieldDefinition;
use App\Models\ProductFieldValue;
use App\Models\ProductPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_lists_only_published_products_and_formats_prices_by_currency_precision(): void
    {
        $this->installCatalogOwner();

        $published = $this->createProduct('Perfume público', 'perfume-publico');
        $this->createProduct('Producto borrador secreto', 'producto-borrador', 'draft');

        ProductPrice::query()->create([
            'product_id' => $published->id,
            'currency_code' => 'USD',
            'amount' => '1234.50',
        ]);

        $expectedPrice = CurrencyCatalog::formatAmount(
            '1234.50',
            'USD',
            app()->getLocale(),
        );

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('Perfume público')
            ->assertSee($expectedPrice)
            ->assertDontSee('Producto borrador secreto')
            ->assertSee('name="description"', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee(route('site.home'), false);
    }

    public function test_catalog_search_and_category_filter_keep_only_matching_published_products(): void
    {
        $this->installCatalogOwner();

        $category = CatalogCategory::query()->firstOrFail();
        $first = $this->createProduct('Filtro cítrico', 'filtro-citrico');
        $second = $this->createProduct('Crema corporal', 'crema-corporal');

        $this->get(route('site.home', ['q' => 'cítrico']))
            ->assertOk()
            ->assertSee($first->name)
            ->assertDontSee($second->name);

        $this->get(route('site.home', ['category' => $category->slug]))
            ->assertOk()
            ->assertSee($first->name)
            ->assertSee($second->name);
    }

    public function test_product_detail_discloses_only_public_custom_fields_and_emits_product_metadata(): void
    {
        $this->installCatalogOwner();

        $category = CatalogCategory::query()->firstOrFail();
        $product = $this->createProduct('Perfume de detalle', 'perfume-detalle');
        $group = CatalogGroup::query()
            ->where('category_id', $category->id)
            ->firstOrFail();

        $publicField = ProductFieldDefinition::query()->create([
            'group_id' => $group->id,
            'field_key' => 'public_note_site_test',
            'label' => 'Nota pública de prueba',
            'field_type' => 'text',
            'options' => [],
            'unit_dimension' => null,
            'unit_code' => null,
            'unit_catalog_code' => null,
            'is_required' => false,
            'is_public' => true,
            'is_searchable' => false,
            'width' => 'full',
            'sort_order' => 900,
        ]);

        $privateField = ProductFieldDefinition::query()->create([
            'group_id' => $group->id,
            'field_key' => 'private_note_site_test',
            'label' => 'Nota interna privada',
            'field_type' => 'text',
            'options' => [],
            'unit_dimension' => null,
            'unit_code' => null,
            'unit_catalog_code' => null,
            'is_required' => false,
            'is_public' => false,
            'is_searchable' => false,
            'width' => 'full',
            'sort_order' => 901,
        ]);

        ProductFieldValue::query()->create([
            'product_id' => $product->id,
            'field_definition_id' => $publicField->id,
            'value' => ['value' => 'Detalle visible al público'],
        ]);

        ProductFieldValue::query()->create([
            'product_id' => $product->id,
            'field_definition_id' => $privateField->id,
            'value' => ['value' => 'INTERNAL-SECRET-7291'],
        ]);

        $this->get(route('site.product', $product->slug))
            ->assertOk()
            ->assertSee('Perfume de detalle')
            ->assertSee('Nota pública de prueba')
            ->assertSee('Detalle visible al público')
            ->assertDontSee('Nota interna privada')
            ->assertDontSee('INTERNAL-SECRET-7291')
            ->assertSee('property="og:type" content="product"', false)
            ->assertSee(route('site.product', $product->slug), false);
    }

    public function test_unpublished_product_detail_returns_not_found(): void
    {
        $this->installCatalogOwner();
        $draft = $this->createProduct('Producto no publicado', 'producto-no-publicado', 'draft');

        $this->get(route('site.product', $draft->slug))
            ->assertNotFound();
    }

    public function test_sitemap_contains_home_and_published_products_but_not_drafts(): void
    {
        $this->installCatalogOwner();
        $published = $this->createProduct('Página indexable', 'pagina-indexable');
        $draft = $this->createProduct('Borrador oculto', 'borrador-oculto', 'draft');

        $this->get(route('site.sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('site.home'), false)
            ->assertSee(route('site.product', $published->slug), false)
            ->assertDontSee(route('site.product', $draft->slug), false);

        $this->get(route('site.robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *')
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /setup')
            ->assertSee(route('site.sitemap'), false);
    }

    private function installCatalogOwner(): void
    {
        $this->post('/setup', [
            'country_code' => 'NI',
            'locale' => 'es-419',
            'business_name' => 'Tienda Site QA',
            'business_type' => 'perfumery',
            'owner_name' => 'Owner Site QA',
            'owner_username' => 'siteqaowner',
            'owner_email' => 'site-qa@example.com',
            'owner_password' => 'Strong-Password-2026!',
            'owner_password_confirmation' => 'Strong-Password-2026!',
            'description' => 'Descripción pública del catálogo para SEO.',
            'primary_color' => '#123456',
            'primary_currency' => 'USD',
            'additional_currencies' => ['NIO'],
            'phones' => [
                ['country_calling_code' => '+505', 'number' => '8888-1234', 'show_public' => '1'],
            ],
            'primary_phone' => '0',
            'photos_enabled' => '0',
        ])->assertRedirect(route('admin.home'));
    }

    private function createProduct(string $name, string $slug, string $status = 'published'): Product
    {
        return Product::query()->create([
            'category_id' => CatalogCategory::query()->firstOrFail()->id,
            'name' => $name,
            'slug' => $slug,
            'description' => 'Descripción de '.$name,
            'status' => $status,
            'image_path' => null,
            'is_featured' => false,
            'sort_order' => 10,
            'quantity_available' => '0.000',
        ]);
    }
}
