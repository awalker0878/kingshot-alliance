<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Services;

use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use App\Contexts\Communications\Delivery\Models\NotificationRoutingPolicy;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class NotificationRoutingPolicyWriteState
{
    public function __construct(private PlayerReferenceQuery $players) {}

    public function set(
        int $recipientUserId,
        ?string $playerId,
        string $scopeKey,
        string $timezone,
        bool $quietHoursEnabled,
        ?string $quietHoursStart,
        ?string $quietHoursEnd,
        bool $allowUrgentDuringQuietHours,
        ?CarbonImmutable $mutedUntil,
        DigestCadence $digestCadence,
        string $dailyDigestTime,
        bool $digestUrgent,
    ): void {
        DB::transaction(function () use (
            $recipientUserId,
            $playerId,
            $scopeKey,
            $timezone,
            $quietHoursEnabled,
            $quietHoursStart,
            $quietHoursEnd,
            $allowUrgentDuringQuietHours,
            $mutedUntil,
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
                    'scope_key' => $scopeKey,
                ],
                [
                    'player_id' => $playerId,
                    'timezone' => $timezone,
                    'quiet_hours_enabled' => $quietHoursEnabled,
                    'quiet_hours_start' => $quietHoursEnabled ? $quietHoursStart : null,
                    'quiet_hours_end' => $quietHoursEnabled ? $quietHoursEnd : null,
                    'allow_urgent_during_quiet_hours' => $allowUrgentDuringQuietHours,
                    'muted_until' => $mutedUntil,
                    'digest_cadence' => $digestCadence->value,
                    'settings' => [
                        'daily_digest_time' => $dailyDigestTime,
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
}
