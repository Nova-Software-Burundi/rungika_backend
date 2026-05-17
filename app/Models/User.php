<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Your logic here to authorize a user
        return $this->hasAnyRole(['super_admin', 'admin', 'operator']);
    }

    public function enableTwoFactorAuthentication(): void
    {
        $this->forceFill([
            'two_factor_secret' => encrypt(app('pragmarx.google2fa')->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode(
                collect(range(1, 8))->map(fn () => Str::random(10))->all()
            )),
        ])->save();
    }

    public function disableTwoFactorAuthentication(): void
    {
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
    }

    public function regenerateRecoveryCodes(): void
    {
        $this->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode(
                collect(range(1, 8))->map(fn () => Str::random(10))->all()
            )),
        ])->save();
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function verifiedContacts()
    {
        return $this->contacts()->whereNotNull('verified_at');
    }

    public function hasVerifiedContact(): bool
    {
        return $this->verifiedContacts()->exists();
    }

    public function driver()
    {
        return $this->hasOne(Driver::class);
    }

    public function initiatedMoneyTransfers()
    {
        return $this->hasMany(MoneyTransfer::class, 'initiated_by');
    }

    public function assignedMoneyTransfers()
    {
        return $this->hasMany(MoneyTransfer::class, 'assigned_agent_id');
    }

}
