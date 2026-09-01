<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Models;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Accounts\Identity\Enums\AuthenticationType;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Global Kingshot Alliance account identity. Game authority belongs to the active Player, not User.
 *
 * @property AuthenticationType $authentication_type
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $deletion_requested_at
 * @property Carbon|null $anonymized_at
 */
final class User extends Authenticatable implements AuditActor, AuthenticatedAccount
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use MustVerifyEmail;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'authentication_type',
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
            'authentication_type' => AuthenticationType::class,
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
        return $this->authentication_type === AuthenticationType::Password && filled($this->getRawOriginal('password'));
    }

    public function supportsGoogleAuthentication(): bool
    {
        return $this->authentication_type === AuthenticationType::Google && blank($this->getRawOriginal('password'));
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
