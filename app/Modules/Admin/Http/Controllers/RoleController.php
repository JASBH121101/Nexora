<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Core\Authorization\PermissionCatalog;
use App\Core\Authorization\RoleCatalog;
use App\Models\ProductFieldDefinition;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class RoleController
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => RoleCatalog::ensureDefaults(),
            'permissionModules' => PermissionCatalog::modules(),
            'productFieldDefinitions' => $this->productFieldDefinitions(),
            'inventoryFieldDefinitions' => PermissionCatalog::inventoryFieldDefinitions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedRole($request);
        $slug = Str::slug($data['name']);

        if ($slug === '') {
            $slug = 'rol';
        }

        abort_if(Role::query()->where('slug', $slug)->exists(), 422, 'Ya existe un rol con ese nombre.');

        Role::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'] ?? [],
            'field_permissions' => $this->sanitizeFieldPermissions($data['field_permissions'] ?? []),
            'is_system' => false,
        ]);

        return back()->with('status', 'Rol creado correctamente.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validatedRole($request);

        $role->forceFill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'] ?? [],
            'field_permissions' => $this->sanitizeFieldPermissions($data['field_permissions'] ?? []),
        ])->save();

        return back()->with('status', 'Permisos del rol actualizados.');
    }

    private function validatedRole(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array', 'max:'.count(PermissionCatalog::keys())],
            'permissions.*' => ['string', 'distinct', Rule::in(PermissionCatalog::keys())],
            'field_permissions' => ['sometimes', 'array'],
            'field_permissions.products' => ['sometimes', 'array'],
            'field_permissions.products.*' => ['sometimes', 'array'],
            'field_permissions.products.*.view' => ['nullable', Rule::in(['allow', 'deny'])],
            'field_permissions.products.*.edit' => ['nullable', Rule::in(['allow', 'deny'])],
            'field_permissions.inventory' => ['sometimes', 'array'],
            'field_permissions.inventory.*' => ['sometimes', 'array'],
            'field_permissions.inventory.*.view' => ['nullable', Rule::in(['allow', 'deny'])],
            'field_permissions.inventory.*.edit' => ['nullable', Rule::in(['allow', 'deny'])],
        ]);
    }

    private function sanitizeFieldPermissions(array $submitted): array
    {
        $allowedFields = [
            'products' => array_keys($this->productFieldDefinitions()),
            'inventory' => array_keys(PermissionCatalog::inventoryFieldDefinitions()),
        ];
        $clean = [];

        foreach ($allowedFields as $resource => $fields) {
            foreach ($fields as $field) {
                foreach (['view', 'edit'] as $action) {
                    $value = data_get($submitted, $resource.'.'.$field.'.'.$action);

                    if (in_array($value, ['allow', 'deny'], true)) {
                        $clean[$resource][$field][$action] = $value;
                    }
                }
            }
        }

        return $clean;
    }

    private function productFieldDefinitions(): array
    {
        return PermissionCatalog::fieldDefinitions() + ProductFieldDefinition::query()
            ->orderBy('field_key')
            ->pluck('label', 'field_key')
            ->all();
    }
}
