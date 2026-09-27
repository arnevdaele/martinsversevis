<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomerPolicy extends PermissionPolicy
{
    protected function group(): string
    {
        return 'customers';
    }

    /** @param  Customer  $record */
    protected function canTouch(User $user, Model $record): bool
    {
        return $user->canSeeCustomerType($record->customer_type_id);
    }

    public function manageLogins(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage-logins') && $this->canTouch($user, $customer);
    }
}
