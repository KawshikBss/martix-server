<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;

class PermissionService
{
    public function hasPermission(User $user, Store $store, string $permission): bool
    {
        // Get user's role in this store
        $role = $store->staff()
            ->where('user_id', $user->id)
            ->with('role.permissions')
            ->first()?->role;

        if (!$role) return false;

        // Owner shortcut
        if ($role->name === 'owner') return true;

        return $role->permissions
            ->contains('name', $permission);
    }
}
