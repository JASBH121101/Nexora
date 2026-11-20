<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Core\Authorization\PermissionCatalog;
use App\Core\Authorization\RoleCatalog;
use App\Models\ProductFieldDefinition;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class EmployeeController
{
    public function index(): View
    {
        $roles = RoleCatalog::ensureDefaults();

        return view('admin.employees.index', [
            'employees' => User::query()
                ->with('roleDefinition')
                ->where('role', 'employee')
                ->orderBy('name')
                ->paginate(20),
            'roles' => $roles,
            'permissionModules' => PermissionCatalog::modules(),
            'productFieldDefinitions' => PermissionCatalog::fieldDefinitions() + ProductFieldDefinition::query()
                ->orderBy('field_key')
                ->pluck('label', 'field_key')
                ->all(),
            'inventoryFieldDefinitions' => PermissionCatalog::inventoryFieldDefinitions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'username' => strtolower(trim((string) $request->input('username'))),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:32',
                'regex:/^[a-z0-9._-]+$/',
                'unique:users,username',
            ],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        $temporaryPassword = Str::password(20);

        User::query()->create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $temporaryPassword,
            'role' => 'employee',
            'role_id' => $data['role_id'] ?? null,
            'is_active' => true,
            'must_change_password' => true,
            'permission_overrides' => [],
        ]);

        return back()->with('employee_credentials_once', [
            'username' => $data['username'],
            'password' => $temporaryPassword,
        ]);
    }

    public function updateRole(Request $request, User $employee): RedirectResponse
    {
        abort_unless($employee->isEmployee(), 404);

        $data = $request->validate([
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        $employee->forceFill([
            'role_id' => $data['role_id'] ?? null,
        ])->save();

        return back()->with('status', 'Rol actualizado para '.$employee->username.'.');
    }

    public function updatePermissions(Request $request, User $employee): RedirectResponse
    {
        abort_unless($employee->isEmployee(), 404);

        $legacyPermissionKeys = array_keys(PermissionCatalog::legacyLabels());

        $request->validate([
            'permissions' => ['sometimes', 'array', 'max:'.count($legacyPermissionKeys)],
            'permissions.*' => ['string', 'distinct', Rule::in($legacyPermissionKeys)],
            'permission_overrides' => ['sometimes', 'array'],
            'permission_overrides.permissions' => ['sometimes', 'array'],
            'permission_overrides.permissions.*' => ['nullable', Rule::in(['allow', 'deny'])],
            'permission_overrides.fields' => ['sometimes', 'array'],
            'permission_overrides.fields.products' => ['sometimes', 'array'],
            'permission_overrides.fields.products.*' => ['sometimes', 'array'],
            'permission_overrides.fields.products.*.view' => ['nullable', Rule::in(['allow', 'deny'])],
            'permission_overrides.fields.products.*.edit' => ['nullable', Rule::in(['allow', 'deny'])],
            'permission_overrides.fields.inventory' => ['sometimes', 'array'],
            'permission_overrides.fields.inventory.*' => ['sometimes', 'array'],
            'permission_overrides.fields.inventory.*.view' => ['nullable', Rule::in(['allow', 'deny'])],
            'permission_overrides.fields.inventory.*.edit' => ['nullable', Rule::in(['allow', 'deny'])],
        ]);

        $changes = [];

        // Keep the previous endpoint contract working for existing owner workflows.
        if ($request->has('permissions')) {
            $changes['permissions'] = array_values(array_unique($request->input('permissions', [])));
        }

        if ($request->has('permission_overrides')) {
            $submitted = $request->input('permission_overrides', []);
            $allowedPermissions = PermissionCatalog::keys();
            $allowedFields = [
                'products' => array_keys(PermissionCatalog::fieldDefinitions() + ProductFieldDefinition::query()
                    ->pluck('label', 'field_key')
                    ->all()),
                'inventory' => array_keys(PermissionCatalog::inventoryFieldDefinitions()),
            ];

            $clean = [
                'permissions' => [],
                'fields' => ['products' => [], 'inventory' => []],
            ];

            foreach (($submitted['permissions'] ?? []) as $safeKey => $value) {
                $permission = str_replace('__', '.', (string) $safeKey);

                if (
                    in_array($permission, $allowedPermissions, true)
                    && in_array($value, ['allow', 'deny'], true)
                ) {
                    $clean['permissions'][$permission] = $value;
                }
            }

            foreach ($allowedFields as $resource => $fields) {
                foreach ($fields as $field) {
                    foreach (['view', 'edit'] as $action) {
                        $value = $submitted['fields'][$resource][$field][$action] ?? null;

                        if (in_array($value, ['allow', 'deny'], true)) {
                            $clean['fields'][$resource][$field][$action] = $value;
                        }
                    }
                }
            }

            $changes['permission_overrides'] = $clean;
        }

        if ($changes !== []) {
            $employee->forceFill($changes)->save();
        }

        return back()->with('status', 'Permisos actualizados para '.$employee->username.'.');
    }

    public function updateStatus(Request $request, User $employee): RedirectResponse
    {
        abort_unless($employee->isEmployee(), 404);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $employee->forceFill([
            'is_active' => (bool) $data['is_active'],
        ])->save();

        return back()->with(
            'status',
            $employee->is_active ? 'Cuenta activada.' : 'Cuenta suspendida.',
        );
    }
}
