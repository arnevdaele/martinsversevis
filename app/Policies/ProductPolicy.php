<?php

namespace App\Policies;

class ProductPolicy extends PermissionPolicy
{
    protected function group(): string
    {
        return 'products';
    }
}
