<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps Filament's abilities onto the "{group}.{ability}" permissions in
 * {@see Permissions}. Super admins never get here: the Gate::before
 * hook in AppServiceProvider has already said yes.
 */
abstract class PermissionPolicy
{
    abstract protected function group(): string;

    /** Record-level narrowing on top of the permission, e.g. customer-type scoping. */
    protected function canTouch(User $user, Model $record): bool
    {
        return true;
    }

    protected function allows(User $user, string $ability): bool
    {
        return $user->can("{$this->group()}.{$ability}");
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Model $record): bool
    {
        return $this->allows($user, 'view') && $this->canTouch($user, $record);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allows($user, 'update') && $this->canTouch($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->allows($user, 'delete') && $this->canTouch($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, 'delete');
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->delete($user, $record);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return $this->allows($user, 'update');
    }
}
