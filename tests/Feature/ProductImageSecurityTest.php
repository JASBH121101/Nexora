<?php

namespace Tests\Feature;

use App\Core\Media\ProductImageProcessor;
use App\Models\CatalogCategory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_raster_is_reencoded_and_kept_out_of_public_storage(): void
    {
        Storage::fake('product_quarantine');
        Storage::fake('private_media');
        Storage::fake('public');

        $source = $this->pngBytes()."\nNEXORA-TRAILING-MARKER";

        $path = app(ProductImageProcessor::class)->process(
            UploadedFile::fake()->createWithContent('client-file-name.png', $source),
        );

        $this->assertMatchesRegularExpression('/^products\/[0-9a-f-]{36}\.png$/i', $path);
        Storage::disk('private_media')->assertExists($path);
        $this->assertSame([], Storage::disk('product_quarantine')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());

        $sanitized = Storage::disk('private_media')->get($path);
        $this->assertIsArray(getimagesizefromstring($sanitized));
        $this->assertStringNotContainsString('NEXORA-TRAILING-MARKER', $sanitized);
    }

    public function test_non_image_payload_is_rejected_and_quarantine_is_cleaned(): void
    {
        Storage::fake('product_quarantine');
        Storage::fake('private_media');
        Storage::fake('public');

        try {
            app(ProductImageProcessor::class)->process(
                UploadedFile::fake()->createWithContent('payload.png', 'plain text that is not an image'),
            );
            $this->fail('A non-image payload must never be stored as a product image.');
        } catch (\RuntimeException) {
            $this->assertSame([], Storage::disk('product_quarantine')->allFiles());
            $this->assertSame([], Storage::disk('private_media')->allFiles());
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_only_published_products_can_serve_their_private_image(): void
    {
        Storage::fake('private_media');
        Storage::fake('product_quarantine');

        $this->installCatalogOwner();

        $category = CatalogCategory::query()->firstOrFail();
        $publishedPath = 'products/'.(string) str()->uuid().'.png';
        $draftPath = 'products/'.(string) str()->uuid().'.png';
        $png = $this->pngBytes();

        Storage::disk('private_media')->put($publishedPath, $png);
        Storage::disk('private_media')->put($draftPath, $png);

        $published = $this->createProduct($category, 'Imagen pública aprobada', 'imagen-publica-aprobada', 'published', $publishedPath);
        $draft = $this->createProduct($category, 'Imagen de borrador', 'imagen-borrador', 'draft', $draftPath);

        $this->get(route('site.product.image', $published->slug))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'public, max-age=300');

        $this->get(route('site.product.image', $draft->slug))
            ->assertNotFound();
    }

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(20, 20);
        $background = imagecolorallocate($image, 35, 90, 120);
        imagefill($image, 0, 0, $background);

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return is_string($bytes) ? $bytes : '';
    }

    private function installCatalogOwner(): void
    {
        $this->post('/setup', [
            'country_code' => 'NI',
            'locale' => 'es-419',
            'business_name' => 'Tienda Image QA',
            'business_type' => 'perfumery',
            'owner_name' => 'Owner Image QA',
            'owner_username' => 'imageqaowner',
            'owner_email' => 'image-qa@example.com',
            'owner_password' => 'Strong-Password-2026!',
            'owner_password_confirmation' => 'Strong-Password-2026!',
            'description' => 'Prueba de imágenes privadas.',
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

    private function createProduct(
        CatalogCategory $category,
        string $name,
        string $slug,
        string $status,
        string $imagePath
    ): Product {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'description' => 'Descripción de '.$name,
            'status' => $status,
            'image_path' => $imagePath,
            'is_featured' => false,
            'sort_order' => 10,
            'quantity_available' => '0.000',
        ]);
    }
}
