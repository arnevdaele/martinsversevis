<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * What a staff member may hand out. Anyone who can edit users or roles could
 * otherwise grant themselves everything, so a non-super-admin can only give
 * away permissions they hold, and only roles made entirely of those. Whatever
 * lies outside that set is left untouched when they save.
 */
final class Grants
{
    public function __construct(private User $actor) {}

    public static function for(User $actor): self
    {
        return new self($actor);
    }

    /** @return list<string> */
    public function permissions(): array
    {
        if ($this->actor->isSuperAdmin()) {
            return Permissions::all();
        }

        return array_values(array_intersect(
            Permissions::all(),
            $this->actor->getAllPermissions()->pluck('name')->all(),
        ));
    }

    public function canGrantPermission(string $name): bool
    {
        return in_array($name, $this->permissions(), true);
    }

    public function canGrantRole(Role $role): bool
    {
        if ($this->actor->isSuperAdmin()) {
            return true;
        }

        if ($role->name === Permissions::SUPER_ADMIN_ROLE) {
            return false;
        }

        return $role->permissions->pluck('name')->diff($this->permissions())->isEmpty();
    }

    /**
     * Merge a form submission into the current assignment without touching
     * anything the actor could not have seen as a choice.
     *
     * @param  list<string>  $current
     * @param  list<string>  $submitted
     * @param  list<string>  $grantable
     * @return list<string>
     */
    public static function merge(array $current, array $submitted, array $grantable): array
    {
        $kept = array_diff($current, $grantable);
        $chosen = array_intersect($submitted, $grantable);

        return array_values(array_unique([...$kept, ...$chosen]));
    }
}
