<?php

namespace App\Filament\Support;

use App\Models\User;
use App\Support\Grants;
use App\Support\Permissions;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Grid;

/**
 * One checkbox list per permission group, generated from {@see Permissions}.
 * The fields are not bound to a relationship: pages read them with
 * {@see self::selected()} and sync through spatie, which also clears its cache.
 */
final class PermissionMatrix
{
    public static function fields(): Grid
    {
        $lists = [];

        foreach (Permissions::groups() as $group => $definition) {
            $options = [];
            foreach ($definition['abilities'] as $ability => $label) {
                $options["{$group}.{$ability}"] = $label;
            }

            $lists[] = CheckboxList::make(self::field($group))
                ->label($definition['label'])
                ->options($options)
                // Disabled boxes may be ticked (held by someone else); they must still pass validation.
                ->in(array_keys($options))
                ->disableOptionWhen(fn (string $value) => ! Grants::for(self::actor())->canGrantPermission($value))
                ->bulkToggleable()
                ->dehydrated(false);
        }

        return Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->schema($lists);
    }

    /** @param list<string> $names  → form state, keyed per group */
    public static function fill(array $names): array
    {
        $state = [];

        foreach (array_keys(Permissions::groups()) as $group) {
            $state[self::field($group)] = array_values(array_filter(
                $names,
                fn (string $name) => str_starts_with($name, "{$group}."),
            ));
        }

        return $state;
    }

    /** @return list<string> every ticked permission across the groups */
    public static function selected(array $rawState): array
    {
        $names = [];

        foreach (array_keys(Permissions::groups()) as $group) {
            $names = [...$names, ...($rawState[self::field($group)] ?? [])];
        }

        return array_values(array_intersect($names, Permissions::all()));
    }

    private static function field(string $group): string
    {
        return 'permissions_'.str_replace('-', '_', $group);
    }

    private static function actor(): User
    {
        return auth()->user();
    }
}
