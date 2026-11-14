<?php

namespace App\Core\Authorization;

use App\Models\Role;
use Illuminate\Support\Collection;

final class RoleCatalog
{
    public static function ensureDefaults(): Collection
    {
        foreach (PermissionCatalog::defaultRoles() as $definition) {
            Role::query()->firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'permissions' => $definition['permissions'],
                    'field_permissions' => [],
                    'is_system' => true,
                ],
            );
        }

        return Role::query()->orderBy('is_system', 'desc')->orderBy('name')->get();
    }
}
