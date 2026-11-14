<?php

namespace App\Core\Authorization;

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

    public static function fieldPermissionFor(string $resource, string $field, string $action): ?string
    {
        return match ([$resource, $field, $action]) {
            ['products', 'image', 'view'] => 'media.view',
            ['products', 'image', 'create'] => 'media.upload',
            ['products', 'image', 'edit'] => 'media.replace',
            ['products', 'price', 'view'],
            ['products', 'name', 'view'],
            ['products', 'description', 'view'],
            ['products', 'category_id', 'view'],
            ['products', 'status', 'view'],
            ['products', 'is_featured', 'view'] => 'products.view',
            ['products', 'price', 'create'],
            ['products', 'name', 'create'],
            ['products', 'description', 'create'],
            ['products', 'category_id', 'create'],
            ['products', 'status', 'create'],
            ['products', 'is_featured', 'create'] => 'products.create',
            ['products', 'price', 'edit'],
            ['products', 'name', 'edit'],
            ['products', 'description', 'edit'],
            ['products', 'category_id', 'edit'],
            ['products', 'status', 'edit'],
            ['products', 'is_featured', 'edit'] => 'products.edit',
            ['products', 'view', 'view'] => 'products.view',
            ['products', 'create', 'create'] => 'products.create',
            ['products', 'edit', 'edit'] => 'products.edit',
            ['inventory', 'quantity', 'view'],
            ['inventory', 'availability', 'view'] => 'inventory.view',
            ['inventory', 'quantity', 'edit'],
            ['inventory', 'availability', 'edit'],
            ['inventory', 'quantity', 'create'],
            ['inventory', 'availability', 'create'] => 'inventory.edit_quantity',
            default => $resource === 'products'
                ? match ($action) {
                    'view' => 'products.view',
                    'create' => 'products.create',
                    'edit' => 'products.edit',
                    default => null,
                }
                : null,
        };
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
                    static fn (string $permission): bool => !str_starts_with($permission, 'employees.'),
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
