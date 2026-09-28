<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OrderPolicy extends PermissionPolicy
{
    protected function group(): string
    {
        return 'orders';
    }

    /** @param  Order  $record */
    protected function canTouch(User $user, Model $record): bool
    {
        return $user->canSeeCustomerType($record->customer->customer_type_id);
    }

    /** The accounting export; it only ever contains orders the user may see. */
    public function export(User $user): bool
    {
        return $this->allows($user, 'export');
    }

    /** Orders come from the portal only. */
    public function create(User $user): bool
    {
        return false;
    }
}
