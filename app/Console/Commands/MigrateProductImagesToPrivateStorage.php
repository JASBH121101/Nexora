<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Media\ProductImageProcessor;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class MigrateProductImagesToPrivateStorage extends Command
{
    protected $signature = 'nexora:secure-product-images
        {--apply : Migrate files and remove public originals after a private backup and database update}';

    protected $description = 'Move legacy product images from public storage into sanitized private media';

    private const MAX_LEGACY_IMAGE_BYTES = 5 * 1024 * 1024;

    public function handle(ProductImageProcessor $processor): int
    {
        $apply = (bool) $this->option('apply');
        $products = Product::query()
            ->whereNotNull('image_path')
            ->orderBy('id')
            ->get();

        $migrated = 0;
        $alreadySecure = 0;
        $missing = 0;
        $failed = 0;

        if (! $apply) {
            $this->warn('DRY RUN: no database records or files will be changed.');
        }

        foreach ($products as $product) {
            $oldPath = $product->image_path;

            if (
                is_string($oldPath)
                && preg_match('/^products\/[0-9a-f-]{36}\.png$/i', $oldPath) === 1
                && Storage::disk('private_media')->exists($oldPath)
            ) {
                $alreadySecure++;

                continue;
            }

            if (
                ! is_string($oldPath)
                || ! str_starts_with($oldPath, 'products/')
                || str_contains($oldPath, '..')
                || ! Storage::disk('public')->exists($oldPath)
            ) {
                $this->warn("Product {$product->id}: legacy image is missing or outside the expected products directory.");
                $missing++;

                continue;
            }

            if (! $apply) {
                $this->line("Would migrate product {$product->id}: {$product->name}");

                continue;
            }

            $temporaryPath = null;
            $newPath = null;

            try {
                $size = Storage::disk('public')->size($oldPath);

                if ($size < 1 || $size > self::MAX_LEGACY_IMAGE_BYTES) {
                    throw new RuntimeException('Legacy image size is outside the supported migration limit.');
                }

                $temporaryPath = tempnam(sys_get_temp_dir(), 'nexora-legacy-image-');

                if (! is_string($temporaryPath) || $temporaryPath === '') {
                    throw new RuntimeException('Could not allocate a private temporary file.');
                }

                $source = Storage::disk('public')->readStream($oldPath);
                $destination = @fopen($temporaryPath, 'wb');

                if (! is_resource($source) || ! is_resource($destination)) {
                    if (is_resource($source)) {
                        fclose($source);
                    }
                    if (is_resource($destination)) {
                        fclose($destination);
                    }
                    throw new RuntimeException('Could not read the legacy image.');
                }

                try {
                    $copied = stream_copy_to_stream($source, $destination, self::MAX_LEGACY_IMAGE_BYTES + 1);
                } finally {
                    fclose($source);
                    fclose($destination);
                }

                if (! is_int($copied) || $copied !== $size || $copied > self::MAX_LEGACY_IMAGE_BYTES) {
                    throw new RuntimeException('Legacy image changed or exceeded the migration limit while being read.');
                }

                $mimeType = Storage::disk('public')->mimeType($oldPath) ?: 'application/octet-stream';
                $upload = new UploadedFile(
                    $temporaryPath,
                    basename($oldPath),
                    $mimeType,
                    null,
                    true,
                );

                // Preserve the original privately before sanitizing or changing the DB path.
                $originalBytes = file_get_contents($temporaryPath);

                if (! is_string($originalBytes) || $originalBytes === '') {
                    throw new RuntimeException('Could not read the legacy image for its private backup.');
                }

                $archivePath = 'legacy-public/'.$product->id.'/'.Str::uuid().'.bin';
                $archived = Storage::disk('legacy_media_archive')->put(
                    $archivePath,
                    $originalBytes,
                    ['visibility' => 'private'],
                );

                if (! $archived) {
                    throw new RuntimeException('Could not create a private backup of the legacy image.');
                }

                $newPath = $processor->process($upload);

                DB::transaction(function () use ($product, $oldPath, $newPath): void {
                    $current = Product::query()
                        ->lockForUpdate()
                        ->findOrFail($product->id);

                    if ($current->image_path !== $oldPath) {
                        throw new RuntimeException('The product image changed during migration.');
                    }

                    $current->forceFill(['image_path' => $newPath])->save();
                });

                if (! Storage::disk('public')->delete($oldPath)) {
                    $this->error("Product {$product->id}: secure image saved, but the old public file could not be removed. Remove it manually after checking the private backup.");
                    $failed++;

                    continue;
                }

                $migrated++;
                $this->info("Product {$product->id}: migrated and backed up privately.");
            } catch (Throwable $exception) {
                if ($newPath !== null) {
                    try {
                        Storage::disk('private_media')->delete($newPath);
                    } catch (Throwable) {
                        $this->warn("Product {$product->id}: could not clean up an uncommitted private image.");
                    }
                }

                $this->error("Product {$product->id}: migration failed ({$exception->getMessage()}).");
                $failed++;
            } finally {
                if (is_string($temporaryPath) && is_file($temporaryPath)) {
                    @unlink($temporaryPath);
                }
            }
        }

        $this->newLine();
        $this->table(
            ['Result', 'Count'],
            [
                ['Migrated', $migrated],
                ['Already private', $alreadySecure],
                ['Missing or unsupported source', $missing],
                ['Would migrate (dry run)', $apply ? 0 : max(0, $products->count() - $alreadySecure - $missing)],
                ['Failed', $failed],
            ],
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
