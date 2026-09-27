<?php

namespace App\Models;

use App\Support\Permissions;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/** Staff: everyone who logs in at /admin. Customers are {@see CustomerUser}. */
#[Fillable(['name', 'email', 'password', 'phone', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** Mirrors the column defaults, so a fresh instance behaves like a stored one. */
    protected $attributes = ['is_active' => true];

    /** @var array{0: list<int>|null}|null Wrapped so a resolved `null` is still a cache hit. */
    protected ?array $resolvedTypeIds = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    /** Types this user is limited to. Empty means every type. */
    public function customerTypes(): BelongsToMany
    {
        return $this->belongsToMany(CustomerType::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Permissions::SUPER_ADMIN_ROLE);
    }

    /**
     * The customer types this user may see, or null for "all of them".
     * Memoised on the instance: it is asked once per table row.
     *
     * @return list<int>|null
     */
    public function visibleCustomerTypeIds(): ?array
    {
        return ($this->resolvedTypeIds ??= [$this->resolveVisibleCustomerTypeIds()])[0];
    }

    /** @return list<int>|null */
    private function resolveVisibleCustomerTypeIds(): ?array
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        $ids = $this->customerTypes()->pluck('customer_types.id')->map(fn ($id) => (int) $id)->all();

        return $ids === [] ? null : $ids;
    }

    public function canSeeCustomerType(int $customerTypeId): bool
    {
        $ids = $this->visibleCustomerTypeIds();

        return $ids === null || in_array($customerTypeId, $ids, true);
    }
}
