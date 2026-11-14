<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Core\Export\CatalogExportBuilder;
use App\Core\Export\ExportRenderer;
use App\Models\CatalogCategory;
use App\Models\ExportHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use ZipArchive;

final class ExportController
{
    public function index(): Response
    {
        $user = auth()->user();
        $canViewHistory = $user->hasPermission('exports.history.view');

        return response()->view('admin.exports.index', [
            'categories' => $user->canAccessField('products', 'category_id', 'view')
                ? CatalogCategory::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'canViewPrices' => $user->canAccessField('products', 'price', 'view'),
            'canViewInventory' => $user->canAccessField('inventory', 'quantity', 'view'),
            'canViewPhotos' => $user->canAccessField('products', 'image', 'view'),
            'canViewHistory' => $canViewHistory,
            'history' => $canViewHistory
                ? ExportHistory::query()->with('user:id,name')->latest()->limit(50)->get()
                : collect(),
            'zipAvailable' => class_exists(ZipArchive::class),
            'canExport' => $user->hasPermission('exports.create') && $user->hasPermission('exports.download'),
        ]);
    }

    public function store(Request $request, CatalogExportBuilder $builder): Response|BinaryFileResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermission('exports.create') && $user->hasPermission('exports.download'),
            403,
        );

        $validated = $request->validate([
            'format' => ['required', Rule::in(['csv', 'json', 'xml', 'zip'])],
            'status' => ['required', Rule::in(['published', 'draft', 'all'])],
            'category_id' => ['nullable', 'integer', 'min:1', 'exists:catalog_categories,id'],
            'product_ids' => ['nullable', 'string', 'max:10000'],
            'field_keys' => ['nullable', 'string', 'max:10000'],
            'include_prices' => ['sometimes', 'boolean'],
            'include_inventory' => ['sometimes', 'boolean'],
            'include_photos' => ['sometimes', 'boolean'],
        ]);

        $productIds = $this->parseIntegerList($validated['product_ids'] ?? '', 'product_ids');
        $fieldKeys = $this->parseStringList($validated['field_keys'] ?? '', 'field_keys');

        $filters = [
            'status' => $validated['status'],
            'category_ids' => isset($validated['category_id']) && $validated['category_id'] !== ''
                ? [(int) $validated['category_id']]
                : [],
            'product_ids' => $productIds,
            'include_prices' => (bool) ($validated['include_prices'] ?? false),
            'include_inventory' => (bool) ($validated['include_inventory'] ?? false),
            'include_photos' => (bool) ($validated['include_photos'] ?? false),
        ];

        if (array_key_exists('field_keys', $validated) && trim((string) $validated['field_keys']) !== '') {
            $filters['field_keys'] = $fieldKeys;
        }

        if ($filters['include_prices'] && ! $user->canAccessField('products', 'price', 'view')) {
            abort(403);
        }
        if ($filters['include_inventory'] && ! $user->canAccessField('inventory', 'quantity', 'view')) {
            abort(403);
        }
        if ($filters['include_photos'] && ! $user->canAccessField('products', 'image', 'view')) {
            abort(403);
        }
        if ($filters['status'] !== 'published' && ! $user->canAccessField('products', 'status', 'view')) {
            abort(403);
        }
        if ($filters['category_ids'] !== [] && ! $user->canAccessField('products', 'category_id', 'view')) {
            abort(403);
        }

        $format = $validated['format'];
        if ($format === 'zip' && ! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages([
                'format' => 'Este servidor no tiene habilitada la extensión PHP ZipArchive.',
            ]);
        }

        $baseName = 'nexora-catalog-'.now()->format('Ymd-His');
        $extension = $format;
        $fileName = $baseName.'.'.$extension;
        $history = null;
        $temporaryPath = null;

        try {
            $dataset = $builder->build($filters, $user);
            $products = $dataset['products'];

            if ($format === 'csv') {
                $contents = ExportRenderer::csv($products);
            } elseif ($format === 'json') {
                $contents = ExportRenderer::json($products);
            } elseif ($format === 'xml') {
                $contents = ExportRenderer::xml($products);
            } else {
                $temporaryPath = $this->createZip($baseName, $products, $filters);
                $contents = null;
            }

            $fileSize = $temporaryPath !== null
                ? (int) filesize($temporaryPath)
                : strlen((string) $contents);

            $history = ExportHistory::query()->create([
                'user_id' => $user->id,
                'format' => $format,
                'status' => 'completed',
                'file_name' => $fileName,
                'file_size_bytes' => $fileSize,
                'record_count' => count($products),
                'filters' => $dataset['filters'],
                'completed_at' => now(),
            ]);

            if ($temporaryPath !== null) {
                return response()->download(
                    $temporaryPath,
                    $fileName,
                    ['Content-Type' => 'application/zip'],
                )->deleteFileAfterSend(true);
            }

            $contentType = match ($format) {
                'csv' => 'text/csv; charset=UTF-8',
                'json' => 'application/json; charset=UTF-8',
                default => 'application/xml; charset=UTF-8',
            };

            return response((string) $contents, 200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
                'Content-Length' => (string) $fileSize,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (ValidationException|HttpExceptionInterface $exception) {
            if ($temporaryPath !== null && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($temporaryPath !== null && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }

            Log::error('NEXORA catalog export failed.', [
                'user_id' => $user->id,
                'format' => $format,
                'exception_class' => $exception::class,
            ]);

            ExportHistory::query()->create([
                'user_id' => $user->id,
                'format' => $format,
                'status' => 'failed',
                'file_name' => $fileName,
                'file_size_bytes' => null,
                'record_count' => 0,
                'filters' => $filters,
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }

    private function parseIntegerList(string $value, string $attribute): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $value));
        foreach ($parts as $part) {
            if ($part === '' || preg_match('/^[1-9][0-9]{0,9}$/D', $part) !== 1) {
                throw ValidationException::withMessages([
                    $attribute => 'Utiliza únicamente identificadores numéricos separados por comas.',
                ]);
            }
        }

        return array_values(array_unique(array_map('intval', $parts)));
    }

    private function parseStringList(string $value, string $attribute): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $value));
        foreach ($parts as $part) {
            if ($part === '' || preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $part) !== 1) {
                throw ValidationException::withMessages([
                    $attribute => 'Hay claves de campos con un formato no válido.',
                ]);
            }
        }

        return array_values(array_unique($parts));
    }

    private function createZip(string $baseName, array $products, array $filters): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'nexora-export-');
        if ($temporaryPath === false) {
            throw new \RuntimeException('No se pudo preparar el archivo temporal de exportación.');
        }

        $zip = new ZipArchive();
        if ($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryPath);
            throw new \RuntimeException('No se pudo crear el paquete ZIP.');
        }

        try {
            $zip->addFromString('catalog/catalog.json', ExportRenderer::json($products));
            $zip->addFromString('catalog/catalog.csv', ExportRenderer::csv($products));
            $zip->addFromString('catalog/catalog.xml', ExportRenderer::xml($products));
            $zip->addFromString('catalog/filters.json', ExportRenderer::json([$filters]));

            if (($filters['include_photos'] ?? false) === true) {
                $disk = Storage::disk('public');
                $root = realpath($disk->path(''));
                if ($root !== false) {
                    $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

                    foreach ($products as $product) {
                        $relativePath = $product['image_path'] ?? null;
                        if (! is_string($relativePath) || $relativePath === '') {
                            continue;
                        }

                        $candidate = $disk->path($relativePath);
                        $realPath = realpath($candidate);
                        if (
                            $realPath === false
                            || ! str_starts_with($realPath, $rootPrefix)
                            || ! is_file($realPath)
                            || is_link($candidate)
                        ) {
                            continue;
                        }

                        $zip->addFile($realPath, 'media/'.$relativePath);
                    }
                }
            }

            $zip->addFromString('README.txt', "NEXORA catalog export\nContains the selected catalog data and any permitted original images.\nImage paths are relative to the media/ directory.\n");
        } catch (Throwable $exception) {
            $zip->close();
            @unlink($temporaryPath);
            throw $exception;
        }

        if (! $zip->close()) {
            @unlink($temporaryPath);
            throw new \RuntimeException('No se pudo finalizar el paquete ZIP.');
        }

        return $temporaryPath;
    }
}
