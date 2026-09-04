<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use App\Contexts\Communications\Delivery\Models\NotificationRoutingPolicy;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SetNotificationRoutingPolicy
{
    public const ACCOUNT_SCOPE = 'account';

    public function __construct(private PlayerReferenceQuery $players) {}

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

        DB::transaction(function () use (
            $recipientUserId,
            $playerId,
            $timezone,
            $quietHoursEnabled,
            $quietHoursStart,
            $quietHoursEnd,
            $allowUrgentDuringQuietHours,
            $mute,
            $digestCadence,
            $dailyDigestTime,
            $digestUrgent,
        ): void {
            if ($playerId !== null) {
                $actor = $this->players->lockCurrent($playerId);
                if ($actor->userId !== $recipientUserId) {
                    throw ValidationException::withMessages([
                        'player' => 'The selected Governor no longer belongs to this account.',
                    ]);
                }
            }

            NotificationRoutingPolicy::query()->updateOrCreate(
                [
                    'recipient_user_id' => $recipientUserId,
                    'scope_key' => $playerId ?? self::ACCOUNT_SCOPE,
                ],
                [
                    'player_id' => $playerId,
                    'timezone' => $timezone,
                    'quiet_hours_enabled' => $quietHoursEnabled,
                    'quiet_hours_start' => $quietHoursEnabled ? $quietHoursStart : null,
                    'quiet_hours_end' => $quietHoursEnabled ? $quietHoursEnd : null,
                    'allow_urgent_during_quiet_hours' => $allowUrgentDuringQuietHours,
                    'muted_until' => $mute,
                    'digest_cadence' => $digestCadence->value,
                    'settings' => [
                        'daily_digest_time' => $dailyDigestTime ?? '09:00',
                        'digest_urgent' => $digestUrgent,
                    ],
                ],
            );
        });
    }

    public function resetGovernorOverride(int $recipientUserId, string $playerId): void
    {
        DB::transaction(function () use ($recipientUserId, $playerId): void {
            $actor = $this->players->lockCurrent($playerId);
            if ($actor->userId !== $recipientUserId) {
                throw ValidationException::withMessages([
                    'player' => 'The selected Governor no longer belongs to this account.',
                ]);
            }

            NotificationRoutingPolicy::query()
                ->where('recipient_user_id', $recipientUserId)
                ->where('scope_key', $playerId)
                ->delete();
        });
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
