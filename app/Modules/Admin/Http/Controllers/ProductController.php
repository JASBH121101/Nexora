<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\BusinessCurrency;
use App\Models\CatalogCategory;
use App\Models\CatalogGroup;
use App\Models\InstallationSettings;
use App\Models\Product;
use App\Models\ProductFieldDefinition;
use App\Models\ProductFieldValue;
use App\Models\ProductPrice;
use App\Core\Authorization\PermissionCatalog;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

final class ProductController
{
    public function index(): View
    {
        $user = auth()->user();
        $products = Product::query()
            ->with(['category', 'prices'])
            ->latest()
            ->paginate(20);

        $products->getCollection()->each(function (Product $product) use ($user): void {
            if (!$user->canAccessField('products', 'image', 'view')) {
                $product->image_path = null;
            }

            if (!$user->canAccessField('products', 'price', 'view')) {
                $product->setRelation('prices', collect());
            }

            if (!$user->canAccessField('products', 'name', 'view')) {
                $product->name = 'Producto restringido';
            }

            if (!$user->canAccessField('products', 'category_id', 'view')) {
                $product->setRelation('category', null);
            }

            if (!$user->canAccessField('products', 'status', 'view')) {
                $product->status = 'restricted';
            }
        });

        return view('admin.products.index', [
            'products' => $products,
            'canCreate' => $user->hasPermission('products.create'),
            'canEdit' => $user->hasPermission('products.edit'),
            'canDelete' => $user->hasPermission('products.delete'),
            'canViewPrice' => $user->canAccessField('products', 'price', 'view'),
            'canViewImage' => $user->canAccessField('products', 'image', 'view'),
            'canViewStatus' => $user->canAccessField('products', 'status', 'view'),
            'canViewName' => $user->canAccessField('products', 'name', 'view'),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertSubmittedFieldsEditable($request);

        if ($request->input('status') === 'published'
            && !$request->user()->hasPermission('products.publish')) {
            abort(403);
        }

        $validated = $this->validateProduct($request);
        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('products', 'public')
            : null;

        try {
            $product = DB::transaction(function () use ($validated, $imagePath): Product {
                $product = Product::query()->create([
                    'category_id' => $validated['category_id'] ?? null,
                    'name' => trim($validated['name']),
                    'slug' => $this->uniqueSlug($validated['name']),
                    'description' => $validated['description'] ?? '',
                    'status' => $validated['status'],
                    'image_path' => $imagePath,
                    'is_featured' => (bool) ($validated['is_featured'] ?? false),
                    'sort_order' => (int) Product::query()->max('sort_order') + 1,
                ]);

                $this->syncProductData($product, $validated);

                return $product;
            });
        } catch (Throwable $exception) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }

        return redirect()->route('admin.products.index')
            ->with('status', 'Producto creado correctamente.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', $this->formData($product));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->assertSubmittedFieldsEditable($request);
        $this->mergeProtectedFields($request, $product);

        if ($request->input('status') === 'published'
            && $product->status !== 'published'
            && !$request->user()->hasPermission('products.publish')) {
            abort(403);
        }

        $validated = $this->validateProduct($request);
        $oldImagePath = $product->image_path;
        $newImagePath = $request->hasFile('image')
            ? $request->file('image')->store('products', 'public')
            : null;

        $removeImage = (bool) ($validated['remove_image'] ?? false);
        $imagePath = $newImagePath ?? ($removeImage ? null : $oldImagePath);

        try {
            DB::transaction(function () use ($product, $validated, $imagePath): void {
                $product->forceFill([
                    'category_id' => $validated['category_id'] ?? null,
                    'name' => trim($validated['name']),
                    'slug' => $this->uniqueSlug($validated['name'], $product->id),
                    'description' => $validated['description'] ?? '',
                    'status' => $validated['status'],
                    'image_path' => $imagePath,
                    'is_featured' => (bool) ($validated['is_featured'] ?? false),
                ])->save();

                $this->syncProductData($product, $validated);
            });
        } catch (Throwable $exception) {
            if ($newImagePath !== null) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }

        if ($oldImagePath !== null && $oldImagePath !== $imagePath) {
            Storage::disk('public')->delete($oldImagePath);
        }

        return redirect()->route('admin.products.index')
            ->with('status', 'Producto actualizado correctamente.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $imagePath = $product->image_path;

        DB::transaction(function () use ($product): void {
            $product->delete();
        });

        if ($imagePath !== null) {
            Storage::disk('public')->delete($imagePath);
        }

        return back()->with('status', 'Producto eliminado.');
    }

    private function formData(?Product $product = null): array
    {
        $user = auth()->user();
        $fieldAction = $product === null ? 'create' : 'edit';
        $product?->load(['fieldValues.definition', 'prices']);
        $values = [];

        foreach ($product?->fieldValues ?? collect() as $fieldValue) {
            $fieldKey = $fieldValue->definition?->field_key;

            if ($fieldKey !== null && $user->canAccessField('products', $fieldKey, 'view')) {
                $values[$fieldKey] = $fieldValue->value['value'] ?? null;
            }
        }

        $settings = InstallationSettings::query()->findOrFail(1);
        $photosEnabled = (bool) data_get($settings->configuration, 'identity.photosEnabled', true)
            && $user->canAccessField('products', 'image', 'view');

        $categories = $user->canAccessField('products', 'category_id', 'view')
            ? CatalogCategory::query()
                ->where(function ($query) use ($product): void {
                    $query->where('is_active', true);

                    if ($product?->category_id !== null) {
                        $query->orWhere('id', $product->category_id);
                    }
                })
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
            : collect();

        $fieldGroups = CatalogGroup::query()
            ->with(['category', 'fields' => static fn ($query) => $query->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $fieldGroups = $fieldGroups->map(function (CatalogGroup $group) use ($user, $fieldAction) {
            $visibleFields = $group->fields
                ->filter(fn (ProductFieldDefinition $field): bool => $user->canAccessField('products', $field->field_key, 'view'))
                ->each(function (ProductFieldDefinition $field) use ($user, $fieldAction): void {
                    $field->setAttribute('can_edit', $user->canAccessField('products', $field->field_key, $fieldAction));
                })
                ->values();

            $group->setRelation('fields', $visibleFields);

            return $group;
        })->filter(static fn (CatalogGroup $group): bool => $group->fields->isNotEmpty())->values();

        $fieldAccess = [];

        foreach (PermissionCatalog::fieldDefinitions() as $fieldKey => $fieldLabel) {
            $fieldAccess[$fieldKey] = [
                'view' => $user->canAccessField('products', $fieldKey, 'view'),
                'edit' => $user->canAccessField('products', $fieldKey, $fieldAction),
            ];
        }

        return [
            'product' => $product,
            'settings' => $settings,
            'categories' => $categories,
            'fieldGroups' => $fieldGroups,
            'currencies' => BusinessCurrency::query()
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->get(),
            'values' => $values,
            'prices' => $user->canAccessField('products', 'price', 'view')
                ? ($product?->prices?->keyBy('currency_code') ?? collect())
                : collect(),
            'photosEnabled' => $photosEnabled,
            'canEditImage' => $user->canAccessField('products', 'image', $fieldAction),
            'canViewImage' => $user->canAccessField('products', 'image', 'view'),
            'canDeleteImage' => $product !== null
                && $user->canAccessField('products', 'image', 'view')
                && $user->hasPermission('media.delete'),
            'canViewPrice' => $user->canAccessField('products', 'price', 'view'),
            'canEditPrice' => $user->canAccessField('products', 'price', $fieldAction),
            'baseFieldAccess' => $fieldAccess,
            'canPublish' => $user->hasPermission('products.publish'),
        ];
    }

    private function assertSubmittedFieldsEditable(Request $request): void
    {
        $user = $request->user();
        $fieldAction = $request->routeIs('admin.products.store') ? 'create' : 'edit';
        $baseFields = [
            'name' => 'name',
            'category_id' => 'category_id',
            'description' => 'description',
            'status' => 'status',
            'is_featured' => 'is_featured',
            'prices' => 'price',
        ];

        foreach ($baseFields as $input => $field) {
            if ($request->exists($input) && !$user->canAccessField('products', $field, $fieldAction)) {
                abort(403);
            }
        }

        if ($request->hasFile('image') && !$user->canAccessField('products', 'image', $fieldAction)) {
            abort(403);
        }

        if ($request->boolean('remove_image')
            && (!$user->canAccessField('products', 'image', 'view') || !$user->hasPermission('media.delete'))) {
            abort(403);
        }

        foreach ((array) $request->input('fields', []) as $fieldKey => $value) {
            if (!$user->canAccessField('products', (string) $fieldKey, $fieldAction)) {
                abort(403);
            }
        }
    }

    private function valuesMatch(mixed $left, mixed $right): bool
    {
        if (is_array($left) || is_array($right)) {
            return json_encode($left) === json_encode($right);
        }

        if ($left === null || $right === null) {
            return $left === $right;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left === (float) $right;
        }

        return (string) $left === (string) $right;
    }

    private function mergeProtectedFields(Request $request, Product $product): void
    {
        $user = $request->user();
        $baseFields = [
            'name' => 'name',
            'category_id' => 'category_id',
            'description' => 'description',
            'status' => 'status',
            'is_featured' => 'is_featured',
        ];

        foreach ($baseFields as $input => $field) {
            if ($user->canAccessField('products', $field, 'edit')) {
                continue;
            }

            $currentValue = $product->{$field};

            if ($request->exists($input) && !$this->valuesMatch($request->input($input), $currentValue)) {
                abort(403);
            }

            $request->merge([$input => $currentValue]);
        }

        if (!$user->canAccessField('products', 'price', 'edit')) {
            $currentPrices = $product->prices()->pluck('amount', 'currency_code')->all();
            $submittedPrices = $request->input('prices');

            if (is_array($submittedPrices)) {
                foreach ($submittedPrices as $currency => $amount) {
                    if (!array_key_exists($currency, $currentPrices)
                        || !$this->valuesMatch($amount, $currentPrices[$currency])) {
                        abort(403);
                    }
                }
            }

            $request->merge(['prices' => $currentPrices]);
        }

        if ($request->hasFile('image') && !$user->canAccessField('products', 'image', 'edit')) {
            abort(403);
        }

        if ($request->boolean('remove_image')
            && (!$user->canAccessField('products', 'image', 'view') || !$user->hasPermission('media.delete'))) {
            abort(403);
        }

        $product->loadMissing(['fieldValues.definition']);
        $currentValues = [];

        foreach ($product->fieldValues as $fieldValue) {
            $key = $fieldValue->definition?->field_key;

            if ($key !== null) {
                $currentValues[$key] = $fieldValue->value['value'] ?? null;
            }
        }

        $categoryId = $user->canAccessField('products', 'category_id', 'edit')
            ? $request->input('category_id', $product->category_id)
            : $product->category_id;

        $definitions = ProductFieldDefinition::query()
            ->availableForCategory($categoryId)
            ->get();
        $submittedFields = (array) $request->input('fields', []);

        foreach ($definitions as $definition) {
            $fieldKey = $definition->field_key;

            if ($user->canAccessField('products', $fieldKey, 'edit')) {
                continue;
            }

            $currentValue = $currentValues[$fieldKey] ?? null;

            if (array_key_exists($fieldKey, $submittedFields)
                && !$this->valuesMatch($submittedFields[$fieldKey], $currentValue)) {
                abort(403);
            }

            $submittedFields[$fieldKey] = $currentValue;
        }

        $request->merge(['fields' => $submittedFields]);
    }

    private function validateProduct(Request $request): array
    {
        $categoryId = filter_var($request->input('category_id'), FILTER_VALIDATE_INT);
        $definitions = ProductFieldDefinition::query()
            ->availableForCategory($categoryId !== false && $categoryId > 0 ? $categoryId : null)
            ->orderBy('sort_order')
            ->get();
        $currencies = BusinessCurrency::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get();

        $fieldKeys = $definitions->pluck('field_key')->all();
        $currencyCodes = $currencies->pluck('code')->all();
        $settings = InstallationSettings::query()->findOrFail(1);
        $photosEnabled = (bool) data_get($settings->configuration, 'identity.photosEnabled', true);

        if ($currencies->isEmpty()) {
            throw ValidationException::withMessages([
                'prices' => 'Activa al menos una moneda antes de registrar productos.',
            ]);
        }

        $fieldArrayRule = $fieldKeys === []
            ? ['sometimes', 'array']
            : ['sometimes', 'array:'.implode(',', $fieldKeys)];

        $fieldAction = $request->routeIs('admin.products.store') ? 'create' : 'edit';
        $canEditPrice = auth()->user()->canAccessField('products', 'price', $fieldAction);
        $priceArrayRule = $canEditPrice
            ? ['required', 'array:'.implode(',', $currencyCodes)]
            : ['sometimes', 'array'];

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('catalog_categories', 'id')
                    ->where(static fn (QueryBuilder $query) => $query->where('is_active', true)),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'is_featured' => ['sometimes', 'boolean'],
            'fields' => $fieldArrayRule,
            'prices' => $priceArrayRule,
            'remove_image' => ['sometimes', 'boolean'],
            'image' => $photosEnabled
                ? ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4000,max_height=4000']
                : ['prohibited'],
        ];

        if ($canEditPrice) {
            foreach ($currencyCodes as $currencyCode) {
                $rules['prices.'.$currencyCode] = ['nullable', 'numeric', 'min:0', 'max:999999999999.99'];
            }
        }

        foreach ($definitions as $definition) {
            $fieldRules = [$definition->is_required ? 'required' : 'nullable'];

            switch ($definition->field_type) {
                case 'textarea':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:5000';
                    break;

                case 'number':
                    $fieldRules[] = 'numeric';
                    $fieldRules[] = 'min:-999999999';
                    $fieldRules[] = 'max:999999999';
                    break;

                case 'select':
                    $fieldRules[] = 'string';
                    if (is_array($definition->options) && $definition->options !== []) {
                        $fieldRules[] = Rule::in($definition->options);
                    }
                    break;

                case 'multiselect':
                    $fieldRules[] = 'array';
                    if (is_array($definition->options) && $definition->options !== []) {
                        $rules['fields.'.$definition->field_key.'.*'] = [
                            'string',
                            Rule::in($definition->options),
                        ];
                    }
                    break;

                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;

                case 'date':
                    $fieldRules[] = 'date';
                    break;

                default:
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:255';
                    break;
            }

            $rules['fields.'.$definition->field_key] = $fieldRules;
        }

        $validated = $request->validate($rules);

        $prices = collect($validated['prices'] ?? [])
            ->filter(static fn ($amount): bool => $amount !== null && $amount !== '');

        if ($canEditPrice && $prices->isEmpty()) {
            throw ValidationException::withMessages([
                'prices' => 'Agrega al menos un precio en una de las monedas habilitadas.',
            ]);
        }

        $validated['prices'] = $canEditPrice ? $prices->all() : [];

        return $validated;
    }

    private function syncProductData(Product $product, array $validated): void
    {
        $user = auth()->user();
        $fieldAction = $product->wasRecentlyCreated ? 'create' : 'edit';
        $definitions = ProductFieldDefinition::query()
            ->availableForCategory($product->category_id)
            ->get()
            ->filter(fn (ProductFieldDefinition $definition): bool => $user->canAccessField(
                'products',
                $definition->field_key,
                $fieldAction,
            ));

        $fieldValues = $validated['fields'] ?? [];

        foreach ($definitions as $definition) {
            $fieldKey = $definition->field_key;

            if (!array_key_exists($fieldKey, $fieldValues)) {
                continue;
            }

            $product->fieldValues()
                ->where('field_definition_id', $definition->id)
                ->delete();

            $value = $fieldValues[$fieldKey];

            if ($definition->field_type === 'boolean') {
                $value = (bool) $value;
            }

            if ($value === null || $value === '') {
                continue;
            }

            ProductFieldValue::query()->create([
                'product_id' => $product->id,
                'field_definition_id' => $definition->id,
                'value' => ['value' => $value],
            ]);
        }

        if ($user->canAccessField('products', 'price', $fieldAction)) {
            $product->prices()->delete();

            foreach ($validated['prices'] ?? [] as $currencyCode => $amount) {
                ProductPrice::query()->create([
                    'product_id' => $product->id,
                    'currency_code' => $currencyCode,
                    'amount' => $amount,
                ]);
            }
        }
    }

    private function uniqueSlug(string $name, ?int $ignoreProductId = null): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'producto';
        }

        $slug = $base;
        $suffix = 2;

        while (
            Product::query()
                ->where('slug', $slug)
                ->when($ignoreProductId, static fn ($query) => $query->where('id', '!=', $ignoreProductId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
