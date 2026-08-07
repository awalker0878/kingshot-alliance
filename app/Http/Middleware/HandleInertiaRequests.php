<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Events\Enums\EventReminderDeliveryStatus;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Models\AllianceMembership;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventReminderDelivery;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;
use LogicException;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'applicationName' => config('app.name'),
            'eventReminders' => $this->eventReminders($request),
        ];
    }

    /** @return list<array{id: string, occurrenceId: string, title: string, startsAt: string, sentAt: string, allianceTimezone: string}> */
    private function eventReminders(Request $request): array
    {
        if (! $request->routeIs('alliance.events.index')) {
            return [];
        }

        $user = $request->user();
        $allianceId = $request->session()->get((string) config('identity.active_alliance_session_key'));

        if (! $user instanceof User || ! is_string($allianceId) || $allianceId === '') {
            return [];
        }

        $membership = AllianceMembership::query()
            ->where('alliance_id', $allianceId)
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active->value)
            ->first();

        if (! $membership instanceof AllianceMembership) {
            return [];
        }

        $deliveries = EventReminderDelivery::query()
            ->where('alliance_id', $allianceId)
            ->where('membership_id', $membership->id)
            ->where('status', EventReminderDeliveryStatus::Sent->value)
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', now()->subDays(7))
            ->with('occurrence.event')
            ->latest('sent_at')
            ->limit(5)
            ->get();

        $reminders = [];

        foreach ($deliveries as $delivery) {
            $occurrence = $delivery->occurrence;
            $event = $occurrence instanceof EventOccurrence ? $occurrence->event : null;

            if (! $occurrence instanceof EventOccurrence || ! $event instanceof Event || $delivery->sent_at === null) {
                throw new LogicException('A sent event reminder must reference its occurrence and event.');
            }

            $reminders[] = [
                'id' => (string) $delivery->id,
                'occurrenceId' => (string) $occurrence->id,
                'title' => (string) $event->title,
                'startsAt' => $occurrence->starts_at->toIso8601String(),
                'sentAt' => $delivery->sent_at->toIso8601String(),
                'allianceTimezone' => (string) $event->timezone,
            ];
        }

        return $reminders;
    }
}
