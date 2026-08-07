<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TwoFactorManager
{
    public function __construct(
        private TotpService $totp,
        private AuditRecorder $audit,
    ) {}

    /** @return array{secret: string, provisioning_uri: string} */
    public function begin(User $user): array
    {
        $secret = $this->totp->generateSecret();

        DB::transaction(function () use ($user, $secret): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->two_factor_confirmed_at !== null) {
                throw ValidationException::withMessages([
                    'two_factor' => 'Two-factor authentication is already enabled.',
                ]);
            }

            $locked->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            $this->audit->record(
                event: 'auth.mfa.enrollment_started',
                actor: $locked,
                subject: $locked,
            );
        });

        return [
            'secret' => $secret,
            'provisioning_uri' => $this->totp->provisioningUri(
                (string) $user->email,
                $secret,
                (string) config('app.name'),
            ),
        ];
    }

    /** @return list<string> */
    public function confirm(User $user, string $code): array
    {
        return DB::transaction(function () use ($user, $code): array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->two_factor_confirmed_at !== null) {
                throw ValidationException::withMessages([
                    'two_factor' => 'Two-factor authentication is already enabled.',
                ]);
            }

            $secret = (string) $locked->two_factor_secret;

            if ($secret === '' || ! $this->totp->verify($secret, $code)) {
                throw ValidationException::withMessages([
                    'code' => 'The authentication code is invalid.',
                ]);
            }

            $plainCodes = $this->generateRecoveryCodes();
            $locked->forceFill([
                'two_factor_recovery_codes' => array_map(
                    static fn (string $recoveryCode): string => hash('sha256', $recoveryCode),
                    $plainCodes,
                ),
                'two_factor_confirmed_at' => now(),
            ])->save();

            $this->audit->record(
                event: 'auth.mfa.enabled',
                actor: $locked,
                subject: $locked,
            );

            $this->outbox($locked, 'auth.mfa.enabled');

            return $plainCodes;
        });
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->two_factor_confirmed_at === null || (string) $locked->two_factor_secret === '') {
                throw ValidationException::withMessages([
                    'two_factor' => 'Two-factor authentication is not enabled.',
                ]);
            }

            $plainCodes = $this->generateRecoveryCodes();
            $locked->forceFill([
                'two_factor_recovery_codes' => array_map(
                    static fn (string $recoveryCode): string => hash('sha256', $recoveryCode),
                    $plainCodes,
                ),
            ])->save();

            $this->audit->record(
                event: 'auth.mfa.recovery_codes_regenerated',
                actor: $locked,
                subject: $locked,
            );

            return $plainCodes;
        });
    }

    public function disable(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $locked->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            $this->audit->record(
                event: 'auth.mfa.disabled',
                actor: $locked,
                subject: $locked,
            );

            $this->outbox($locked, 'auth.mfa.disabled');
        });
    }

    public function verifyTotp(User $user, string $code): bool
    {
        $secret = (string) $user->two_factor_secret;

        return $user->two_factor_confirmed_at !== null
            && $secret !== ''
            && $this->totp->verify($secret, $code);
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $normalized = strtolower(trim($code));

        if ($normalized === '') {
            return false;
        }

        return DB::transaction(function () use ($user, $normalized): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $codes = $locked->two_factor_recovery_codes;

            if (! is_array($codes)) {
                return false;
            }

            $candidate = hash('sha256', $normalized);

            foreach ($codes as $index => $hash) {
                if (is_string($hash) && hash_equals($hash, $candidate)) {
                    unset($codes[$index]);
                    $locked->forceFill([
                        'two_factor_recovery_codes' => array_values($codes),
                    ])->save();

                    $this->audit->record(
                        event: 'auth.mfa.recovery_code_used',
                        actor: $locked,
                        subject: $locked,
                    );

                    return true;
                }
            }

            return false;
        });
    }

    /** @return list<string> */
    private function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($index = 0; $index < 8; $index++) {
            $raw = bin2hex(random_bytes(8));
            $codes[] = strtolower(substr($raw, 0, 4).'-'.substr($raw, 4, 4).'-'.substr($raw, 8, 4).'-'.substr($raw, 12, 4));
        }

        return $codes;
    }

    private function outbox(User $user, string $eventType): void
    {
        OutboxMessage::query()->create([
            'alliance_id' => null,
            'event_type' => $eventType,
            'aggregate_type' => User::class,
            'aggregate_id' => (string) $user->id,
            'idempotency_key' => $eventType.':'.$user->id.':'.now()->format('Uu'),
            'payload' => ['user_id' => $user->id],
            'occurred_at' => now(),
            'available_at' => now(),
            'attempts' => 0,
        ]);
    }
}
