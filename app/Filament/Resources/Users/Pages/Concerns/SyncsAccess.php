<?php

namespace App\Filament\Resources\Users\Pages\Concerns;

use App\Filament\Support\PermissionMatrix;
use App\Models\User;
use App\Support\Grants;
use Spatie\Permission\Models\Role;

/** Roles and direct permissions are written through spatie, with {@see Grants} as the gatekeeper. */
trait SyncsAccess
{
    protected function syncAccess(User $user): void
    {
        $state = $this->form->getRawState();
        $grants = Grants::for(auth()->user());

        $grantableRoles = Role::where('guard_name', 'web')->with('permissions')->get()
            ->filter(fn (Role $role) => $grants->canGrantRole($role))
            ->pluck('name')
            ->all();

        $user->syncRoles(Grants::merge(
            $user->roles->pluck('name')->all(),
            $state['role_names'] ?? [],
            $grantableRoles,
        ));

        $user->syncPermissions(Grants::merge(
            $user->getDirectPermissions()->pluck('name')->all(),
            PermissionMatrix::selected($state),
            $grants->permissions(),
        ));
    }

    protected function accessFormState(User $user): array
    {
        return ['role_names' => $user->roles->pluck('name')->all()]
            + PermissionMatrix::fill($user->getDirectPermissions()->pluck('name')->all());
    }
}
