<?php

namespace App\Filament\Support;

use App\Models\CustomerType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Customer type pickers only offer the types the signed-in user may see. */
final class CustomerTypeScope
{
    public static function query(Builder $query): Builder
    {
        /** @var User $user */
        $user = auth()->user();
        $ids = $user?->visibleCustomerTypeIds();

        return $query
            ->when($ids !== null, fn (Builder $q) => $q->whereIn('id', $ids))
            ->orderBy('sort_order');
    }

    /** @return array<int, string> */
    public static function options(): array
    {
        return self::query(CustomerType::query())->pluck('name', 'id')->all();
    }
}
