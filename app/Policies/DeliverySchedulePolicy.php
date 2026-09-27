<?php

namespace App\Policies;

use App\Models\DeliverySchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DeliverySchedulePolicy extends PermissionPolicy
{
    protected function group(): string
    {
        return 'delivery';
    }

    /**
     * The default is what every customer without a schedule falls back to;
     * make another one the default first.
     *
     * @param  DeliverySchedule  $record
     */
    public function delete(User $user, Model $record): bool
    {
        return ! $record->is_default && parent::delete($user, $record);
    }
}
