<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;

class RolePolicy extends PermissionPolicy
{
    protected function group(): string
    {
        return 'roles';
    }

    /** The super admin role has no permissions to edit: it bypasses them. */
    protected function canTouch(User $user, Model $record): bool
    {
        return $record->name !== Permissions::SUPER_ADMIN_ROLE;
    }
}
