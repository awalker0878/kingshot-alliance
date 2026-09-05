<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Services;

use App\Contexts\Accounts\Identity\Queries\AccountTimezoneQuery;
use App\Contexts\Accounts\Identity\Queries\VerifiedNotificationEmailQuery;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use App\Contexts\Communications\Delivery\Enums\NotificationUrgency;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Models\NotificationPreference;
use App\Contexts\Communications\Delivery\Models\NotificationRoutingPolicy;
use App\Contexts\Communications\Delivery\ValueObjects\EffectiveRoutingPolicy;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\Communications\Delivery\ValueObjects\ResolvedDeliveryPlan;
use App\Contexts\Communications\Delivery\ValueObjects\ResolvedDeliveryRoute;
use Carbon\CarbonImmutable;

final readonly class NotificationRouteResolver
{
    public const ACCOUNT_SCOPE = 'account';

    private const MAX_ENDPOINT_ROUTES = 20;

    public function __construct(
        private VerifiedNotificationEmailQuery $email,
        private AccountTimezoneQuery $timezones,
    ) {}

    public function resolve(NotificationIntent $intent): ResolvedDeliveryPlan
    {
        $routes = [];

        if ($this->isEnabled(
            $intent->recipientUserId,
            $intent->playerId,
            $intent->notificationType,
            DeliveryChannel::InApp,
        )) {
            $routes[] = new ResolvedDeliveryRoute(
                channel: DeliveryChannel::InApp,
                endpointId: null,
                targetLabel: null,
                dueAt: $intent->availableAt,
                reason: 'in_app',
            );
        }

        /** @var list<?string> $routingPlayerIds */
        $routingPlayerIds = $intent->playerId !== null
            ? [$intent->playerId]
            : ($intent->eligiblePlayerIds !== [] ? array_values(array_unique($intent->eligiblePlayerIds)) : [null]);

        $storedChannels = [
            DeliveryChannel::Discord,
            DeliveryChannel::Telegram,
            DeliveryChannel::WebPush,
        ];
        $seenEndpointIds = [];

        foreach ($storedChannels as $channel) {
            foreach ($routingPlayerIds as $routingPlayerId) {
                if (! $this->isEnabled(
                    $intent->recipientUserId,
                    $routingPlayerId,
                    $intent->notificationType,
                    $channel,
                )) {
                    continue;
                }

                $policy = $this->effectivePolicy($intent->recipientUserId, $routingPlayerId);
                foreach ($this->endpoints($intent->recipientUserId, $routingPlayerId, $channel) as $endpoint) {
                    $endpointId = (string) $endpoint->id;
                    if (isset($seenEndpointIds[$endpointId])) {
                        continue;
                    }
                    $seenEndpointIds[$endpointId] = true;

                    [$dueAt, $reason, $cadence] = $this->externalSchedule($intent, $policy);
                    $routes[] = new ResolvedDeliveryRoute(
                        channel: $channel,
                        endpointId: $endpointId,
                        targetLabel: (string) $endpoint->label,
                        dueAt: $dueAt,
                        reason: $reason,
                        digestCadence: $cadence,
                    );
                }
            }
        }

        if ($this->isEnabled(
            $intent->recipientUserId,
            $intent->playerId,
            $intent->notificationType,
            DeliveryChannel::Email,
        ) && $this->email->forUser($intent->recipientUserId) !== null) {
            [$dueAt, $reason, $cadence] = $this->externalSchedule(
                $intent,
                $this->effectivePolicy($intent->recipientUserId, $intent->playerId),
            );
            $routes[] = new ResolvedDeliveryRoute(
                channel: DeliveryChannel::Email,
                endpointId: null,
                targetLabel: 'Verified account email',
                dueAt: $dueAt,
                reason: $reason,
                digestCadence: $cadence,
            );
        }

        return new ResolvedDeliveryPlan($routes);
    }

    public function isEnabled(
        int $recipientUserId,
        ?string $playerId,
        string $notificationType,
        DeliveryChannel $channel,
    ): bool {
        $query = NotificationPreference::query()
            ->where('recipient_user_id', $recipientUserId)
            ->where('notification_type', $notificationType)
            ->where('channel', $channel->value);

        if ($playerId !== null) {
            $preference = (clone $query)
                ->where('scope_key', $playerId)
                ->first();
            if ($preference instanceof NotificationPreference) {
                return $preference->enabled;
            }
        }

        $accountPreference = (clone $query)
            ->where('scope_key', self::ACCOUNT_SCOPE)
            ->first();
        if ($accountPreference instanceof NotificationPreference) {
            return $accountPreference->enabled;
        }

        return $channel !== DeliveryChannel::Email;
    }

    public function effectivePolicy(int $recipientUserId, ?string $playerId): EffectiveRoutingPolicy
    {
        $policy = null;
        if ($playerId !== null) {
            $policy = NotificationRoutingPolicy::query()
                ->where('recipient_user_id', $recipientUserId)
                ->where('scope_key', $playerId)
                ->first();
        }

        $policy ??= NotificationRoutingPolicy::query()
            ->where('recipient_user_id', $recipientUserId)
            ->where('scope_key', self::ACCOUNT_SCOPE)
            ->first();

        if (! $policy instanceof NotificationRoutingPolicy) {
            return new EffectiveRoutingPolicy(
                timezone: $this->timezones->forUser($recipientUserId),
                quietHoursEnabled: false,
                quietHoursStart: null,
                quietHoursEnd: null,
                allowUrgentDuringQuietHours: false,
                mutedUntil: null,
                digestCadence: DigestCadence::Immediate,
            );
        }

        return new EffectiveRoutingPolicy(
            timezone: $policy->timezone,
            quietHoursEnabled: $policy->quiet_hours_enabled,
            quietHoursStart: $policy->quiet_hours_start,
            quietHoursEnd: $policy->quiet_hours_end,
            allowUrgentDuringQuietHours: $policy->allow_urgent_during_quiet_hours,
            mutedUntil: $policy->muted_until,
            digestCadence: $policy->digest_cadence,
            settings: is_array($policy->settings) ? $policy->settings : [],
        );
    }

    /** @return list<NotificationEndpoint> */
    private function endpoints(int $recipientUserId, ?string $playerId, DeliveryChannel $channel): array
    {
        return array_values(NotificationEndpoint::query()
            ->where('recipient_user_id', $recipientUserId)
            ->where('channel', $channel->value)
            ->where('enabled', true)
            ->where(static function ($query) use ($playerId): void {
                if ($playerId === null) {
                    $query->whereNull('player_id');

                    return;
                }

                $query->where(static function ($scope) use ($playerId): void {
                    $scope->where('player_id', $playerId)->orWhereNull('player_id');
                });
            })
            ->when(
                $playerId !== null,
                static fn ($query) => $query->orderByRaw('CASE WHEN player_id = ? THEN 0 ELSE 1 END', [$playerId]),
            )
            ->orderBy('created_at')
            ->limit(self::MAX_ENDPOINT_ROUTES)
            ->get()
            ->all());
    }

    /** @return array{CarbonImmutable,string,DigestCadence} */
    private function externalSchedule(
        NotificationIntent $intent,
        EffectiveRoutingPolicy $policy,
    ): array {
        $dueAt = $intent->availableAt;
        $reason = 'immediate';
        $cadence = $policy->digestCadence;

        $digestUrgent = ($policy->settings['digest_urgent'] ?? false) === true;
        if ($intent->urgency === NotificationUrgency::Urgent && ! $digestUrgent) {
            $cadence = DigestCadence::Immediate;
        }

        $digestDue = $this->digestDueAt($dueAt, $policy, $cadence);
        if ($digestDue->greaterThan($dueAt)) {
            $dueAt = $digestDue;
            $reason = 'digest_'.$cadence->value;
        }

        if ($policy->mutedUntil instanceof CarbonImmutable && $policy->mutedUntil->greaterThan($dueAt)) {
            $dueAt = $policy->mutedUntil;
            $reason = 'temporary_mute';
        }

        $quietDue = $this->quietHoursEnd($dueAt, $policy, $intent->urgency);
        if ($quietDue->greaterThan($dueAt)) {
            $dueAt = $quietDue;
            $reason = 'quiet_hours';
        }

        return [$dueAt, $reason, $cadence];
    }

    private function digestDueAt(
        CarbonImmutable $dueAt,
        EffectiveRoutingPolicy $policy,
        DigestCadence $cadence,
    ): CarbonImmutable {
        if ($cadence === DigestCadence::Immediate) {
            return $dueAt;
        }

        $local = $dueAt->setTimezone($policy->timezone);
        if ($cadence === DigestCadence::Hourly) {
            return $local->startOfHour()->addHour()->utc();
        }

        $time = is_string($policy->settings['daily_digest_time'] ?? null)
            ? (string) $policy->settings['daily_digest_time']
            : '09:00';
        [$hour, $minute] = $this->clock($time, 9, 0);
        $candidate = $local->setTime($hour, $minute, 0);
        if (! $candidate->greaterThan($local)) {
            $candidate = $candidate->addDay();
        }

        return $candidate->utc();
    }

    private function quietHoursEnd(
        CarbonImmutable $dueAt,
        EffectiveRoutingPolicy $policy,
        NotificationUrgency $urgency,
    ): CarbonImmutable {
        if (! $policy->quietHoursEnabled
            || $policy->quietHoursStart === null
            || $policy->quietHoursEnd === null
            || ($urgency === NotificationUrgency::Urgent && $policy->allowUrgentDuringQuietHours)) {
            return $dueAt;
        }

        [$startHour, $startMinute] = $this->clock($policy->quietHoursStart, 0, 0);
        [$endHour, $endMinute] = $this->clock($policy->quietHoursEnd, 0, 0);
        if ($startHour === $endHour && $startMinute === $endMinute) {
            return $dueAt;
        }

        $local = $dueAt->setTimezone($policy->timezone);
        $minutes = ($local->hour * 60) + $local->minute;
        $start = ($startHour * 60) + $startMinute;
        $end = ($endHour * 60) + $endMinute;

        $inside = $start < $end
            ? $minutes >= $start && $minutes < $end
            : $minutes >= $start || $minutes < $end;
        if (! $inside) {
            return $dueAt;
        }

        $candidate = $local->setTime($endHour, $endMinute, 0);
        if ($start > $end && $minutes >= $start) {
            $candidate = $candidate->addDay();
        }

        return $candidate->utc();
    }

    /** @return array{int,int} */
    private function clock(string $value, int $defaultHour, int $defaultMinute): array
    {
        if (! preg_match('/^(\d{2}):(\d{2})$/', $value, $matches)) {
            return [$defaultHour, $defaultMinute];
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        if ($hour > 23 || $minute > 59) {
            return [$defaultHour, $defaultMinute];
        }

        return [$hour, $minute];
    }
}
