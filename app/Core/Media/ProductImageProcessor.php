<?php

declare(strict_types=1);

namespace App\Core\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ProductImageProcessor
{
    private const MAX_INPUT_BYTES = 5 * 1024 * 1024;

    private const MAX_OUTPUT_BYTES = 15 * 1024 * 1024;

    private const MAX_DIMENSION = 4000;

    private const MAX_PIXELS = 16_000_000;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * Store an untrusted upload in private quarantine, decode it and re-encode
     * only raster pixels to a fresh PNG in private product-media storage.
     *
     * This sanitizes the published representation; it is not an antivirus scan.
     */
    public function process(UploadedFile $upload): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagepng')) {
            throw new RuntimeException('El procesamiento seguro de imágenes no está disponible.');
        }

        if (! $upload->isValid()) {
            throw new RuntimeException('No se pudo recibir la imagen correctamente.');
        }

        $quarantinePath = null;
        $storedPath = null;
        $image = null;

        try {
            $quarantinePath = $upload->store('incoming', 'product_quarantine');

            if (! is_string($quarantinePath) || $quarantinePath === '') {
                throw new RuntimeException('No se pudo aislar la imagen para su inspección.');
            }

            $input = Storage::disk('product_quarantine')->get($quarantinePath);

            if (! is_string($input) || $input === '' || strlen($input) > self::MAX_INPUT_BYTES) {
                throw new RuntimeException('La imagen supera el tamaño permitido o no pudo leerse.');
            }

            $metadata = @getimagesizefromstring($input);

            if (! is_array($metadata)) {
                throw new RuntimeException('El archivo recibido no contiene una imagen válida.');
            }

            $mimeType = $metadata['mime'] ?? null;
            $width = $metadata[0] ?? 0;
            $height = $metadata[1] ?? 0;

            if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
                throw new RuntimeException('El formato de imagen no está permitido.');
            }

            if (
                ! is_int($width)
                || ! is_int($height)
                || $width < 1
                || $height < 1
                || $width > self::MAX_DIMENSION
                || $height > self::MAX_DIMENSION
                || ($width * $height) > self::MAX_PIXELS
            ) {
                throw new RuntimeException('Las dimensiones de la imagen superan el límite permitido.');
            }

            $image = @imagecreatefromstring($input);

            if (! $image instanceof \GdImage) {
                throw new RuntimeException('No fue posible decodificar la imagen de forma segura.');
            }

            if (imagesx($image) !== $width || imagesy($image) !== $height) {
                throw new RuntimeException('Los datos internos de la imagen no coinciden con sus dimensiones.');
            }

            imagealphablending($image, false);
            imagesavealpha($image, true);

            ob_start();
            $encoded = imagepng($image, null, 6);
            $sanitized = ob_get_clean();

            if (! $encoded || ! is_string($sanitized) || $sanitized === '') {
                throw new RuntimeException('No fue posible normalizar la imagen.');
            }

            if (strlen($sanitized) > self::MAX_OUTPUT_BYTES) {
                throw new RuntimeException('La imagen procesada supera el tamaño permitido.');
            }

            $storedPath = 'products/'.Str::uuid().'.png';
            $written = Storage::disk('private_media')->put(
                $storedPath,
                $sanitized,
                ['visibility' => 'private'],
            );

            if (! $written) {
                throw new RuntimeException('No fue posible guardar la imagen procesada.');
            }

            return $storedPath;
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                try {
                    Storage::disk('private_media')->delete($storedPath);
                } catch (Throwable) {
                    Log::warning('NEXORA could not remove an uncommitted product image.');
                }
            }

            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('No fue posible procesar la imagen de forma segura.');
        } finally {
            if ($image instanceof \GdImage) {
                imagedestroy($image);
            }

            if ($quarantinePath !== null) {
                try {
                    Storage::disk('product_quarantine')->delete($quarantinePath);
                } catch (Throwable) {
                    Log::warning('NEXORA could not remove a quarantined product image.');
                }
            }
        }
    }
}
