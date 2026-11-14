<?php

namespace App\Models;

use App\Core\Authorization\PermissionCatalog;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'username',
    'email',
    'password',
    'role',
    'role_id',
    'is_active',
    'must_change_password',
    'permissions',
    'permission_overrides',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    use MustVerifyEmailTrait;
    use Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'permissions' => 'array',
            'permission_overrides' => 'array',
        ];
    }

    public function roleDefinition(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        if (! $this->isEmployee() || ! $this->is_active) {
            return false;
        }

        $userOverrides = $this->permission_overrides ?? [];
        $override = $userOverrides['permissions'][$permission] ?? null;

        if ($override === 'allow') {
            return true;
        }

        if ($override === 'deny') {
            return false;
        }

        $rolePermissions = $this->roleDefinition?->permissions ?? [];

        if (in_array($permission, $rolePermissions, true)) {
            return true;
        }

        $legacyPermissions = $this->permissions ?? [];

        if (in_array($permission, $legacyPermissions, true)) {
            return true;
        }

        foreach (PermissionCatalog::legacyGrantsFor($permission) as $legacyPermission) {
            if (in_array($legacyPermission, $legacyPermissions, true)) {
                return true;
            }
        }

        return false;
    }

    public function canAccessField(string $resource, string $field, string $action): bool
    {
        $permission = PermissionCatalog::fieldPermissionFor($resource, $field, $action);

        if ($permission === null || ! $this->hasPermission($permission)) {
            return false;
        }

        if ($action !== 'view' && ! $this->canAccessField($resource, $field, 'view')) {
            return false;
        }

        $fieldAction = $action === 'create' ? 'edit' : $action;
        $userOverrides = $this->permission_overrides ?? [];
        $userFieldRule = $userOverrides['fields'][$resource][$field][$fieldAction] ?? null;

        if ($userFieldRule === 'allow') {
            return true;
        }

        if ($userFieldRule === 'deny') {
            return false;
        }

        $roleFieldPermissions = $this->roleDefinition?->field_permissions ?? [];
        $roleRule = $roleFieldPermissions[$resource][$field][$fieldAction] ?? null;

        return $roleRule !== 'deny';
    }
}
