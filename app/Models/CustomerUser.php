<?php

namespace App\Models;

use App\Notifications\CustomerInvitation;
use App\Notifications\CustomerResetPassword;
use App\Support\Locales;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Password;

/** A person who logs in to the customer portal on behalf of a {@see Customer}. */
#[Fillable(['customer_id', 'name', 'email', 'password', 'is_active', 'receives_order_confirmations', 'locale', 'invited_at', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class CustomerUser extends Authenticatable implements HasLocalePreference
{
    use HasFactory, Notifiable;

    /** Mirrors the column defaults, so a fresh instance behaves like a stored one. */
    protected $attributes = ['is_active' => true, 'receives_order_confirmations' => true];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'receives_order_confirmations' => 'boolean',
            'invited_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Mails and the portal speak this language: the login's own choice, else the customer's. */
    public function preferredLocale(): string
    {
        foreach ([$this->locale, $this->customer?->locale] as $locale) {
            if (Locales::supports($locale)) {
                return $locale;
            }
        }

        return Locales::default();
    }

    public function hasAcceptedInvitation(): bool
    {
        return $this->password !== null;
    }

    /** A login works only while both the person and their company are active. */
    public function canLogIn(): bool
    {
        return $this->is_active && $this->customer?->is_active && ! $this->customer->trashed();
    }

    /** Invitations reuse the reset broker: same token, a welcoming mail, a longer life. */
    public function sendInvitation(): void
    {
        $token = Password::broker('customers')->createToken($this);

        $this->notify(new CustomerInvitation($token));
        $this->forceFill(['invited_at' => now()])->save();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomerResetPassword($token));
    }
}
