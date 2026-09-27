<?php

namespace App\Policies;

class ProductCategoryPolicy extends PermissionPolicy
{
    protected function group(): string
    {
        return 'products';
    }
}
