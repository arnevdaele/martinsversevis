<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends PermissionPolicy
{
    protected function group(): string
    {
        return 'users';
    }

    /**
     * Only a super admin may touch a super admin — otherwise anyone with
     * `users.update` could lock the owner out or take over the account.
     *
     * @param  User  $record
     */
    protected function canTouch(User $user, Model $record): bool
    {
        return $user->isSuperAdmin() || ! $record->isSuperAdmin();
    }

    /** @param  User  $record */
    public function delete(User $user, Model $record): bool
    {
        return $user->isNot($record) && parent::delete($user, $record);
    }
}
