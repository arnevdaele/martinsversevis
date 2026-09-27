<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-admin {--name=} {--email=}')]
#[Description('Create a super admin (the first login on a fresh install)')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $this->call('permissions:sync');

        $name = $this->option('name') ?: text('Naam', required: true);
        $email = $this->option('email') ?: text('E-mail', required: true);
        $password = password('Wachtwoord (min. 12 tekens)', required: true);

        $validator = Validator::make(compact('email', 'password'), [
            'email' => ['email', 'unique:users,email'],
            'password' => ['min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        User::create(compact('name', 'email', 'password'))->assignRole(Permissions::SUPER_ADMIN_ROLE);

        $this->components->info("Super admin {$email} aangemaakt. Aanmelden via /admin.");

        return self::SUCCESS;
    }
}
