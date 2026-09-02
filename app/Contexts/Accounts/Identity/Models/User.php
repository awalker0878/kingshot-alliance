<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Models;

use App\Contexts\Accounts\Credentials\Notifications\ResetKingshotAlliancePassword;
use App\Contexts\Accounts\EmailVerification\Notifications\VerifyKingshotAllianceEmail;
use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Global Kingshot Alliance account identity. Game authority belongs to the active Player, not User.
 *
 * Sign-in methods are attached credentials; User is not classified by an authentication type.
 *
 * @property string|null $password
 * @property string|null $pending_email
 * @property Carbon|null $pending_email_requested_at
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $deletion_requested_at
 * @property Carbon|null $anonymized_at
 */
final class User extends Authenticatable implements AuditActor, AuthenticatedAccount, PasskeyUser
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use MustVerifyEmail;
    use Notifiable;
    use PasskeyAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'pending_email_requested_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    /** @return HasMany<AccountIdentity, $this> */
    public function accountIdentities(): HasMany
    {
        return $this->hasMany(AccountIdentity::class);
    }

    public function supportsPasswordAuthentication(): bool
    {
        return filled($this->getRawOriginal('password'));
    }

    public function supportsGoogleAuthentication(): bool
    {
        return $this->accountIdentities()->where('provider', 'google')->exists();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyKingshotAllianceEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        if (! $this->supportsPasswordAuthentication()) {
            return;
        }

        $this->notify(new ResetKingshotAlliancePassword((string) $token));
    }

    public function accountName(): string
    {
        return (string) $this->name;
    }

    public function accountEmail(): string
    {
        return (string) $this->email;
    }

    public function auditUserId(): int
    {
        return (int) $this->id;
    }

    public function auditPlayerId(): ?string
    {
        return null;
    }
}
