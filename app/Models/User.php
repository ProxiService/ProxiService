<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden([
    'password',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'remember_token',
])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable;

    public function hasVerifiedEmail(): bool
    {
        return ! config('fortify.email_verification_required') || parent::hasVerifiedEmail();
    }

    /**
     * Définit les conversions automatiques des attributs.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Vérifie si l'utilisateur est un client.
     */
    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    /**
     * Vérifie si l'utilisateur est un prestataire.
     */
    public function isPrestataire(): bool
    {
        return $this->role === 'prestataire';
    }

    /**
     * Vérifie si l'utilisateur est un administrateur.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * @return HasOne<ProviderProfile, $this>
     */
    public function providerProfile(): HasOne
    {
        return $this->hasOne(ProviderProfile::class);
    }
}
