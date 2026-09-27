<?php

namespace App\Policies;

use App\Models\CustomerType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomerTypePolicy extends PermissionPolicy
{
    protected function group(): string
    {
        return 'customer-types';
    }

    /** @param  CustomerType  $record */
    public function delete(User $user, Model $record): bool
    {
        // System types anchor the defaults; a type still in use would orphan customers.
        return ! $record->is_system
            && ! $record->customers()->withTrashed()->exists()
            && parent::delete($user, $record);
    }
}
