<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use App\Contexts\Communications\Delivery\Services\NotificationRoutingPolicyWriteState;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

final readonly class SetNotificationRoutingPolicy
{
    public const ACCOUNT_SCOPE = 'account';

    public function __construct(private NotificationRoutingPolicyWriteState $writeState) {}

    public function handle(
        int $recipientUserId,
        ?string $playerId,
        string $timezone,
        bool $quietHoursEnabled,
        ?string $quietHoursStart,
        ?string $quietHoursEnd,
        bool $allowUrgentDuringQuietHours,
        ?DateTimeInterface $mutedUntil,
        DigestCadence $digestCadence,
        ?string $dailyDigestTime = null,
        bool $digestUrgent = false,
    ): void {
        $timezone = trim($timezone);
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw ValidationException::withMessages(['timezone' => 'Choose a valid timezone.']);
        }

        $quietHoursStart = $this->clock($quietHoursStart, 'quiet_hours_start');
        $quietHoursEnd = $this->clock($quietHoursEnd, 'quiet_hours_end');
        if ($quietHoursEnabled
            && ($quietHoursStart === null || $quietHoursEnd === null || $quietHoursStart === $quietHoursEnd)) {
            throw ValidationException::withMessages([
                'quiet_hours' => 'Quiet hours require distinct start and end times.',
            ]);
        }

        $dailyDigestTime = $this->clock($dailyDigestTime, 'daily_digest_time');
        if ($digestCadence === DigestCadence::Daily && $dailyDigestTime === null) {
            $dailyDigestTime = '09:00';
        }

        $mute = $mutedUntil === null ? null : CarbonImmutable::instance($mutedUntil)->utc();
        if ($mute instanceof CarbonImmutable && $mute->greaterThan(CarbonImmutable::now('UTC')->addDays(30))) {
            throw ValidationException::withMessages([
                'muted_until' => 'Temporary mute cannot extend more than 30 days.',
            ]);
        }
        if ($mute instanceof CarbonImmutable && ! $mute->isFuture()) {
            $mute = null;
        }

        $this->writeState->set(
            recipientUserId: $recipientUserId,
            playerId: $playerId,
            scopeKey: $playerId ?? self::ACCOUNT_SCOPE,
            timezone: $timezone,
            quietHoursEnabled: $quietHoursEnabled,
            quietHoursStart: $quietHoursStart,
            quietHoursEnd: $quietHoursEnd,
            allowUrgentDuringQuietHours: $allowUrgentDuringQuietHours,
            mutedUntil: $mute,
            digestCadence: $digestCadence,
            dailyDigestTime: $dailyDigestTime ?? '09:00',
            digestUrgent: $digestUrgent,
        );
    }

    public function resetGovernorOverride(int $recipientUserId, string $playerId): void
    {
        $this->writeState->resetGovernorOverride($recipientUserId, $playerId);
    }

    private function clock(?string $value, string $field): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/D', $value) !== 1) {
            throw ValidationException::withMessages([$field => 'Use a 24-hour HH:MM time.']);
        }

        return $value;
    }
}
