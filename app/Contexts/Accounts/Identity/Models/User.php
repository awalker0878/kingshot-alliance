<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Models;

use App\Contexts\Accounts\Credentials\Actions\QueuePasswordResetDelivery;
use App\Contexts\Accounts\EmailVerification\Actions\RequestEmailVerification;
use App\Contexts\Accounts\EmailVerification\Enums\EmailVerificationTarget;
use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use SensitiveParameter;

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

    public function isActive(): bool
    {
        return $this->anonymized_at === null;
    }

    /** Call on the current account after acquiring its row lock, before ordinary writes. */
    public function ensureActive(): void
    {
        if (! $this->isActive()) {
            throw ValidationException::withMessages(['account' => 'This account has already been deleted.']);
        }
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
        app(RequestEmailVerification::class)->handle((int) $this->id, EmailVerificationTarget::Account);
    }

    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
    {
        app(QueuePasswordResetDelivery::class)->handle((int) $this->id, (string) $token);
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
