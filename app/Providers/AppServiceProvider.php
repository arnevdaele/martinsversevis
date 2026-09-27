<?php

namespace App\Providers;

use App\Models\CustomerUser;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\Permissions;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        JsonResource::withoutWrapping();

        // Super admins hold every *permission*, but not every ability: the
        // structural rules in the policies (never delete yourself, never
        // delete a system customer type) still apply to them.
        $permissions = array_flip(Permissions::all());
        Gate::before(function ($user, string $ability) use ($permissions) {
            if ($user instanceof User && isset($permissions[$ability]) && $user->isSuperAdmin()) {
                return true;
            }

            return null;
        });

        Gate::policy(Role::class, RolePolicy::class);

        // See App\Mail\Concerns\RespectsMailQuota. Kept under the provider's cap for headroom.
        RateLimiter::for('outgoing-mail', fn () => Limit::perHour((int) config('mail.hourly_limit')));

        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User || $event->user instanceof CustomerUser) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }
}
