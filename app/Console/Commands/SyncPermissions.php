<?php

namespace App\Console\Commands;

use App\Support\Permissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

#[Signature('permissions:sync {--prune : Delete permissions that are no longer defined}')]
#[Description('Write the permissions from App\Support\Permissions to the database and create missing default roles')]
class SyncPermissions extends Command
{
    public function handle(): int
    {
        $defined = Permissions::all();

        foreach ($defined as $name) {
            Permission::findOrCreate($name, Permissions::GUARD);
        }

        if ($this->option('prune')) {
            $removed = Permission::where('guard_name', Permissions::GUARD)->whereNotIn('name', $defined)->delete();
            $this->components->info("Removed {$removed} stale permission(s).");
        }

        // Default roles are only seeded once: after that they belong to the client.
        foreach (Permissions::defaultRoles() as $name => $permissions) {
            $role = Role::where('name', $name)->where('guard_name', Permissions::GUARD)->first();

            if (! $role) {
                Role::create(['name' => $name, 'guard_name' => Permissions::GUARD])->syncPermissions($permissions);
                $this->components->info("Created role [{$name}].");
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->components->info(count($defined).' permissions in sync.');

        return self::SUCCESS;
    }
}
