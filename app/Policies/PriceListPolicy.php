<?php

namespace App\Policies;

class PriceListPolicy extends PermissionPolicy
{
    protected function group(): string
    {
        return 'price-lists';
    }
}
