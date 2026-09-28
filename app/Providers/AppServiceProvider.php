<?php

namespace App\Providers;

use App\Http\Responses\AdminLoginResponse;
use App\Models\CustomerUser;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\Permissions;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
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
        $this->app->bind(LoginResponse::class, AdminLoginResponse::class);
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

        $this->configureAdminDates();

        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User || $event->user instanceof CustomerUser) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }

    /**
     * Dutch dates in the admin: 28/09/2026, weeks from Monday. The browser's own
     * date input follows the computer's language (often English, mm/dd/yyyy),
     * so pickers use Filament's calendar instead. Applies to DatePicker too.
     */
    private function configureAdminDates(): void
    {
        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker
            ->native(false)
            ->firstDayOfWeek(1)
            ->defaultDateDisplayFormat('d/m/Y')
            ->defaultDateTimeDisplayFormat('d/m/Y H:i')
            ->defaultDateTimeWithSecondsDisplayFormat('d/m/Y H:i:s'));

        Table::configureUsing(fn (Table $table) => $table
            ->defaultDateDisplayFormat('d/m/Y')
            ->defaultDateTimeDisplayFormat('d/m/Y H:i'));

        Schema::configureUsing(fn (Schema $schema) => $schema
            ->defaultDateDisplayFormat('d/m/Y')
            ->defaultDateTimeDisplayFormat('d/m/Y H:i'));
    }
}
