<?php

namespace Tests;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->artisan('permissions:sync');
    }

    protected function superAdmin(): User
    {
        return User::factory()->create()->assignRole(Permissions::SUPER_ADMIN_ROLE);
    }

    /** @param list<string> $permissions */
    protected function staffWith(array $permissions): User
    {
        $role = Role::create(['name' => 'test-'.uniqid(), 'guard_name' => Permissions::GUARD])->syncPermissions($permissions);

        return User::factory()->create()->assignRole($role);
    }
}
