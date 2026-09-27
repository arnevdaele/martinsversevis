<?php

namespace App\Policies;

class DeliveryExceptionPolicy extends PermissionPolicy
{
    protected function group(): string
    {
        return 'delivery';
    }
}
