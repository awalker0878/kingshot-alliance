<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\KingPerks\Support;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\KingPerks\Enums\KingAppointmentType;
use App\Contexts\Operations\KingPerks\Models\KingPerkPlan;
use App\Contexts\Operations\KingPerks\Services\KingPerkScheduler;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Carbon\CarbonImmutable;
use LogicException;

/** Actual Kingdom authority, Event and scheduled appointment, not a delivery-policy mock. */
final class KingPerkReminderSourceFixture
{
    /** @return array{plan: string, appointment: string, event: string} */
    public function forPlayer(PlayerReference $player): array
    {
        app(BootstrapKingdomAdministrator::class)->handle($player->kingdomId, $player->playerId);
        $scope = EventTypeScope::query()->where('scope', EventScope::Kingdom->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'kingdom-of-power'))->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $player->playerId,
            configurationId: (string) $scope->id,
            scope: EventScope::Kingdom,
            targetId: $player->kingdomId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            title: 'Notification King Perks fixture',
            durationMinutes: 60,
            settings: ['preparation_phase_minutes' => 1440],
        );
        $occurrenceId = $created->firstOccurrenceId ?? throw new LogicException('The fixture must create an occurrence.');
        $scheduler = app(KingPerkScheduler::class);
        $scheduler->createPlan($player->playerId, $created->eventId, $occurrenceId);
        $plan = KingPerkPlan::query()->where('occurrence_id', $occurrenceId)->firstOrFail();
        $appointment = $scheduler->assignAppointment(
            $player->playerId, (string) $plan->id, KingAppointmentType::NobleAdvisor,
            $player->playerId, CarbonImmutable::now('UTC')->addHour(),
        );
        $scheduler->publishPlan($player->playerId, (string) $plan->id);

        return ['plan' => (string) $plan->id, 'appointment' => $appointment, 'event' => $created->eventId];
    }
}
