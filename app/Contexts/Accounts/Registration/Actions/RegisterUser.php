<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Registration\Actions;

use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Registration\Data\RegisteredAccount;
use App\Contexts\Accounts\Registration\Data\RegistrationProviderIdentity;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class RegisterUser
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        string $name,
        string $email,
        ?string $password,
        string $timezone = 'UTC',
        bool $emailVerified = false,
        ?RegistrationProviderIdentity $providerIdentity = null,
    ): RegisteredAccount {
        if ($password !== null && blank($password)) {
            throw new InvalidArgumentException('A configured local password cannot be blank.');
        }

        $provider = $providerIdentity === null ? null : Str::lower(trim($providerIdentity->provider));
        $providerSubject = $providerIdentity === null ? null : trim($providerIdentity->subject);
        $providerEmail = $providerIdentity?->email === null
            ? null
            : Str::lower(trim($providerIdentity->email));

        if ($providerIdentity !== null && ($provider === '' || $providerSubject === '')) {
            throw new InvalidArgumentException('Provider and provider subject are required.');
        }

        $user = DB::transaction(function () use (
            $name,
            $email,
            $password,
            $timezone,
            $emailVerified,
            $providerIdentity,
            $provider,
            $providerSubject,
            $providerEmail,
        ): User {
            $user = User::query()->create([
                'name' => trim($name),
                'email' => Str::lower(trim($email)),
                'password' => $password,
                'timezone' => $timezone,
            ]);

            if ($emailVerified) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            if ($providerIdentity !== null && $provider !== null && $providerSubject !== null) {
                AccountIdentity::query()->create([
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_subject' => $providerSubject,
                    'provider_email' => $providerEmail,
                    'provider_email_verified_at' => $providerIdentity->emailVerified ? now() : null,
                    'linked_at' => now(),
                    'last_used_at' => now(),
                ]);

                $this->audit->record(
                    event: 'auth.'.$provider.'.identity_created',
                    actor: $user,
                    subject: $user,
                    metadata: ['provider' => $provider],
                );
            }

            $this->audit->record(
                event: 'user.registered',
                actor: $user,
                subject: $user,
                metadata: [
                    'timezone' => $user->timezone,
                    'email_verified_at_registration' => $emailVerified,
                    'password_configured' => $password !== null,
                ],
            );

            OutboxMessage::query()->create([
                'alliance_id' => null,
                'event_type' => 'user.registered',
                'aggregate_type' => User::class,
                'aggregate_id' => (string) $user->id,
                'idempotency_key' => 'user.registered:'.$user->id,
                'payload' => ['user_id' => $user->id],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return $user;
        });

        if (! $emailVerified) {
            $user->sendEmailVerificationNotification();
        }

        return new RegisteredAccount(
            userId: (int) $user->id,
            email: (string) $user->email,
        );
    }
}
