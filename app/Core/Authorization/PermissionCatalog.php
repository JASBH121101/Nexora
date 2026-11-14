<?php

namespace App\Core\Authorization;

use App\Models\ProductFieldDefinition;

final class PermissionCatalog
{
    public static function modules(): array
    {
        return [
            'products' => [
                'label' => 'Productos',
                'permissions' => [
                    'view' => 'Consultar',
                    'create' => 'Crear',
                    'edit' => 'Editar',
                    'delete' => 'Eliminar',
                    'publish' => 'Publicar u ocultar',
                ],
            ],
            'categories' => [
                'label' => 'Categorías',
                'permissions' => [
                    'view' => 'Consultar',
                    'create' => 'Crear',
                    'edit' => 'Editar',
                    'delete' => 'Eliminar',
                ],
            ],
            'fields' => [
                'label' => 'Plantillas y campos',
                'permissions' => [
                    'view' => 'Consultar',
                    'create' => 'Crear',
                    'edit' => 'Editar',
                    'delete' => 'Eliminar',
                ],
            ],
            'media' => [
                'label' => 'Fotografías',
                'permissions' => [
                    'view' => 'Consultar',
                    'upload' => 'Subir',
                    'replace' => 'Reemplazar',
                    'delete' => 'Eliminar',
                ],
            ],
            'inventory' => [
                'label' => 'Inventario básico',
                'permissions' => [
                    'view' => 'Consultar existencias',
                    'edit_quantity' => 'Modificar cantidades',
                ],
            ],
            'exports' => [
                'label' => 'Exportaciones',
                'permissions' => [
                    'create' => 'Generar exportaciones',
                    'download' => 'Descargar archivos',
                    'history.view' => 'Consultar historial',
                ],
            ],
            'employees' => [
                'label' => 'Empleados',
                'permissions' => [
                    'view' => 'Consultar empleados',
                    'create' => 'Crear cuentas',
                    'edit' => 'Editar cuentas y roles',
                    'suspend' => 'Suspender o activar cuentas',
                ],
            ],
            'settings' => [
                'label' => 'Configuración',
                'permissions' => [
                    'view' => 'Consultar configuración',
                    'edit' => 'Modificar configuración',
                ],
            ],
            'reports' => [
                'label' => 'Reportes',
                'permissions' => [
                    'view' => 'Consultar reportes',
                    'export' => 'Exportar reportes',
                ],
            ],
        ];
    }

    public static function permissions(): array
    {
        $permissions = [];

        foreach (self::modules() as $module => $definition) {
            foreach ($definition['permissions'] as $action => $label) {
                $permissions[$module.'.'.$action] = $definition['label'].' — '.$label;
            }
        }

        return $permissions;
    }

    public static function keys(): array
    {
        return array_keys(self::permissions());
    }

    public static function fieldDefinitions(): array
    {
        return [
            'name' => 'Nombre del producto',
            'description' => 'Descripción',
            'category_id' => 'Categoría',
            'status' => 'Estado de publicación',
            'is_featured' => 'Producto destacado',
            'price' => 'Precios',
            'image' => 'Fotografía principal',
        ];
    }

    public static function inventoryFieldDefinitions(): array
    {
        return [
            'quantity' => 'Cantidad disponible',
            'availability' => 'Estado de disponibilidad',
        ];
    }

    public static function isKnownField(string $resource, string $field): bool
    {
        if ($resource === 'products') {
            return array_key_exists($field, self::fieldDefinitions())
                || ProductFieldDefinition::query()
                    ->where('field_key', $field)
                    ->exists();
        }

        if ($resource === 'inventory') {
            return array_key_exists($field, self::inventoryFieldDefinitions());
        }

        return false;
    }

    public static function fieldPermissionFor(string $resource, string $field, string $action): ?string
    {
        if ($resource === 'products') {
            if ($field === 'image') {
                return match ($action) {
                    'view' => 'media.view',
                    'create' => 'media.upload',
                    'edit' => 'media.replace',
                    default => null,
                };
            }

            return match ($action) {
                'view' => 'products.view',
                'create' => 'products.create',
                'edit' => 'products.edit',
                default => null,
            };
        }

        if ($resource === 'inventory' && in_array($field, ['quantity', 'availability'], true)) {
            return match ($action) {
                'view' => 'inventory.view',
                'create', 'edit' => 'inventory.edit_quantity',
                default => null,
            };
        }

        return null;
    }

    public static function legacyLabels(): array
    {
        return [
            'catalog.view' => 'Consultar productos del catálogo',
            'catalog.manage' => 'Crear, editar y publicar productos',
            'categories.manage' => 'Administrar categorías',
            'fields.manage' => 'Configurar grupos y campos dinámicos',
            'media.manage' => 'Gestionar fotografías',
            'reports.view' => 'Consultar reportes',
        ];
    }

    public static function legacyGrantsFor(string $permission): array
    {
        return match ($permission) {
            'products.view' => ['catalog.view', 'catalog.manage'],
            'products.create', 'products.edit', 'products.delete', 'products.publish' => ['catalog.manage'],
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete' => ['categories.manage'],
            'fields.view', 'fields.create', 'fields.edit', 'fields.delete' => ['fields.manage'],
            'media.view', 'media.upload', 'media.replace', 'media.delete' => ['media.manage'],
            'reports.view', 'reports.export' => ['reports.view'],
            default => [],
        };
    }

    public static function defaultRoles(): array
    {
        $all = self::keys();

        return [
            [
                'name' => 'Administrador',
                'slug' => 'admin',
                'description' => 'Administra las operaciones del negocio sin transferir la titularidad.',
                'permissions' => array_values(array_filter(
                    $all,
                    static fn (string $permission): bool => ! str_starts_with($permission, 'employees.'),
                )),
            ],
            [
                'name' => 'Editor de catálogo',
                'slug' => 'editor',
                'description' => 'Crea y mantiene productos, categorías y fotografías.',
                'permissions' => [
                    'products.view', 'products.create', 'products.edit', 'products.publish',
                    'categories.view', 'fields.view', 'media.view', 'media.upload', 'media.replace',
                ],
            ],
            [
                'name' => 'Solo consulta',
                'slug' => 'viewer',
                'description' => 'Consulta el catálogo y los reportes autorizados.',
                'permissions' => ['products.view', 'categories.view', 'fields.view', 'media.view', 'reports.view'],
            ],
        ];
    }
}
