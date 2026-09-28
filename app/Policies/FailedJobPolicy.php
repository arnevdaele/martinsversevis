<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Failed mails are only looked at, sent again or thrown away; never made or edited by hand. */
class FailedJobPolicy extends PermissionPolicy
{
    protected function group(): string
    {
        return 'failed-mails';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
    {
        return false;
    }

    public function retry(User $user): bool
    {
        return $this->allows($user, 'retry');
    }
}
