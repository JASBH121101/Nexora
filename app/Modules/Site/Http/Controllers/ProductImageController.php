<?php

declare(strict_types=1);

namespace App\Modules\Site\Http\Controllers;

use App\Models\InstallationSettings;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProductImageController
{
    public function showPublic(string $slug): StreamedResponse
    {
        $product = Product::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $this->assertPhotosEnabled();

        abort_if($product->image_path === null, 404);

        return $this->stream($product->image_path, true);
    }

    public function admin(Request $request, Product $product): StreamedResponse
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $user->canAccessField('products', 'image', 'view'),
            403,
        );

        $this->assertPhotosEnabled();

        abort_if($product->image_path === null, 404);

        return $this->stream($product->image_path, false);
    }

    private function assertPhotosEnabled(): void
    {
        $settings = InstallationSettings::query()->findOrFail(1);

        abort_unless(
            (bool) data_get($settings->configuration, 'identity.photosEnabled', true),
            404,
        );
    }

    private function stream(string $path, bool $public): StreamedResponse
    {
        // ProductImageProcessor creates only opaque UUID-based PNG paths.
        abort_unless(
            preg_match('/^products\/[0-9a-f-]{36}\.png$/i', $path) === 1,
            404,
        );

        $stream = Storage::disk('private_media')->readStream($path);

        abort_if(! is_resource($stream), 404);

        return response()->stream(
            static function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            },
            200,
            [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename="product.png"',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
                'Cache-Control' => $public ? 'public, max-age=300' : 'private, no-store',
                'Vary' => 'Cookie',
            ],
        );
    }
}
