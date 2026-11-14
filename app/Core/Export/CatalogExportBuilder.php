<?php

namespace App\Core\Export;

use App\Models\Product;
use App\Models\ProductFieldDefinition;
use Illuminate\Validation\ValidationException;

final class CatalogExportBuilder
{
    /**
     * @return array{products: array<int, array<string, mixed>>, filters: array<string, mixed>}
     */
    public function build(array $filters, object $user): array
    {
        $includePrices = (bool) ($filters['include_prices'] ?? false);
        $includeInventory = (bool) ($filters['include_inventory'] ?? false);
        $includePhotos = (bool) ($filters['include_photos'] ?? false);

        if ($includePrices && ! $user->canAccessField('products', 'price', 'view')) {
            abort(403);
        }

        if ($includeInventory && ! $user->canAccessField('inventory', 'quantity', 'view')) {
            abort(403);
        }

        if ($includePhotos && ! $user->canAccessField('products', 'image', 'view')) {
            abort(403);
        }

        $status = $filters['status'] ?? 'published';
        if ($status !== 'published' && ! $user->canAccessField('products', 'status', 'view')) {
            abort(403);
        }

        $categoryIds = array_values(array_unique(array_map('intval', $filters['category_ids'] ?? [])));
        $productIds = array_values(array_unique(array_map('intval', $filters['product_ids'] ?? [])));

        if ($categoryIds !== [] && ! $user->canAccessField('products', 'category_id', 'view')) {
            abort(403);
        }

        $definitions = ProductFieldDefinition::query()
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (ProductFieldDefinition $definition): bool => $user->canAccessField(
                'products',
                $definition->field_key,
                'view',
            ));

        if (array_key_exists('field_keys', $filters)) {
            $requestedFields = array_values(array_unique($filters['field_keys'] ?? []));
            $known = ProductFieldDefinition::query()
                ->whereIn('field_key', $requestedFields)
                ->pluck('field_key')
                ->all();

            if (count($known) !== count($requestedFields)) {
                throw ValidationException::withMessages([
                    'field_keys' => 'Uno o más campos seleccionados no existen.',
                ]);
            }

            if (array_diff($requestedFields, $definitions->pluck('field_key')->all()) !== []) {
                abort(403);
            }

            $definitions = $definitions
                ->whereIn('field_key', $requestedFields)
                ->values();
        }

        $query = Product::query()
            ->with([
                'category',
                'prices',
                'fieldValues.definition',
            ])
            ->orderBy('id')
            ->limit(5000);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        if ($productIds !== []) {
            $query->whereIn('id', $productIds);
        }

        $products = $query->get()->map(function (Product $product) use (
            $user,
            $definitions,
            $includePrices,
            $includeInventory,
            $includePhotos,
        ): array {
            $canViewName = $user->canAccessField('products', 'name', 'view');
            $canViewDescription = $user->canAccessField('products', 'description', 'view');
            $canViewCategory = $user->canAccessField('products', 'category_id', 'view');
            $canViewStatus = $user->canAccessField('products', 'status', 'view');
            $canViewFeatured = $user->canAccessField('products', 'is_featured', 'view');

            $fields = [];
            foreach ($product->fieldValues as $fieldValue) {
                $key = $fieldValue->definition?->field_key;
                if ($key === null || ! $definitions->contains('field_key', $key)) {
                    continue;
                }

                $fields[$key] = data_get($fieldValue->value, 'value');
            }

            $prices = [];
            if ($includePrices) {
                foreach ($product->prices as $price) {
                    $prices[$price->currency_code] = (string) $price->amount;
                }
            }

            $imagePath = $includePhotos && $user->canAccessField('products', 'image', 'view')
                ? $this->safeImagePath($product->image_path)
                : null;

            return [
                'id' => (int) $product->id,
                'name' => $canViewName ? $product->name : null,
                'slug' => $canViewName ? $product->slug : null,
                'category' => $canViewCategory ? $product->category?->name : null,
                'status' => $canViewStatus ? $product->status : null,
                'description' => $canViewDescription ? $product->description : null,
                'is_featured' => $canViewFeatured ? (bool) $product->is_featured : null,
                'quantity_available' => $includeInventory ? (string) $product->quantity_available : null,
                'prices' => $prices,
                'image_path' => $imagePath,
                'fields' => $fields,
            ];
        })->all();

        return [
            'products' => $products,
            'filters' => [
                'status' => $status,
                'category_ids' => $categoryIds,
                'product_ids' => $productIds,
                'field_keys' => $definitions->pluck('field_key')->values()->all(),
                'include_prices' => $includePrices,
                'include_inventory' => $includeInventory,
                'include_photos' => $includePhotos,
                'result_limit' => 5000,
            ],
        ];
    }

    private function safeImagePath(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', $path);
        if (
            str_starts_with($normalized, '/')
            || preg_match('/(^|\/)\.\.($|\/)/', $normalized) === 1
            || preg_match('/^[A-Za-z]:/', $normalized) === 1
            || str_contains($normalized, "\0")
        ) {
            return null;
        }

        return $normalized;
    }
}
