<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'kyc_status',
        'kyc_verified_at',
        'kyc_tier',
        'flagged',
        'flagged_reason',
        'trading_enabled',
        'country_id',
        'preferred_currency_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'kyc_verified_at' => 'datetime',
            'kyc_tier' => 'integer',
            'flagged' => 'boolean',
            'trading_enabled' => 'boolean',
            'password' => 'hashed',
        ];
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

    public function initiatedMoneyTransfers()
    {
        return $this->hasMany(MoneyTransfer::class, 'initiated_by');
    }

    public function assignedMoneyTransfers()
    {
        return $this->hasMany(MoneyTransfer::class, 'assigned_agent_id');
    }

    public function ratingsGiven()
    {
        return $this->hasMany(UserRating::class, 'rater_id');
    }

    public function ratingsReceived()
    {
        return $this->hasMany(UserRating::class, 'rated_user_id');
    }

    public function averageRating()
    {
        return $this->ratingsReceived()->avg('rating');
    }

    public function ratingCount()
    {
        return $this->ratingsReceived()->count();
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function preferredCurrency()
    {
        return $this->belongsTo(Currency::class, 'preferred_currency_id');
    }

    public function apiKeys()
    {
        return $this->hasMany(\App\Models\ApiKey::class);
    }
}
