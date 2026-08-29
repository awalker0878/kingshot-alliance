<?php

declare(strict_types=1);

namespace App\ReadModels\CommandOverview\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventStatus;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventTypeProfileResolver;
use App\ReadModels\EventManagement\Queries\EventCommandQuery;
use Illuminate\Database\Eloquent\Builder;

/** Read-only, deterministic presentation groups for officer brief delivery. */
final readonly class OfficerBriefQuery
{
    private const CLOSEOUT_LOOKBACK_DAYS = 14;

    private const CLOSEOUT_CANDIDATE_LIMIT = 12;

    public function __construct(
        private AllianceAuthorization $allianceAuthorization,
        private EventAuthorization $eventAuthorization,
        private EventTypeProfileResolver $profiles,
        private EventCommandQuery $eventCommand,
    ) {}

    /**
     * @param  array{asOf:string,actionCount:int,items:list<array<string,mixed>>}|null  $allianceCommand
     * @return list<array<string,mixed>>
     */
    public function for(
        PlayerReference $actor,
        string $allianceId,
        ?array $allianceCommand,
    ): array {
        if ($allianceCommand === null || ! $this->allianceAuthorization->allows(
            $actor->playerId,
            $allianceId,
            AlliancePermission::MembershipManage,
        )) {
            return [];
        }

        $nextEvent = null;
        foreach ($allianceCommand['items'] as $item) {
            if (($item['code'] ?? null) === 'next_event') {
                $nextEvent = $item;
                break;
            }
        }

        return [
            $this->daily($allianceCommand),
            $this->upcomingEvent($nextEvent),
            $this->postEvent($actor, $allianceId),
        ];
    }

    /**
     * @param  array{asOf:string,actionCount:int,items:list<array<string,mixed>>}  $command
     * @return array<string,mixed>
     */
    private function daily(array $command): array
    {
        $facts = array_map(
            static fn (array $item): array => [
                'code' => (string) ($item['code'] ?? ''),
                'owner' => (string) ($item['owner'] ?? ''),
                'state' => (string) ($item['state'] ?? ''),
                'count' => (int) ($item['count'] ?? 0),
                'actionable' => ($item['actionable'] ?? false) === true,
                'handoff' => is_array($item['handoff'] ?? null) ? $item['handoff'] : null,
            ],
            $command['items'],
        );

        return $this->brief(
            group: 'daily_officer',
            state: $command['actionCount'] > 0 ? 'needs_attention' : 'clear',
            count: $command['actionCount'],
            owner: 'read_models.alliance_command',
            canonicalUrl: '/',
            facts: $facts,
        );
    }

    /**
     * @param  array<string,mixed>|null  $event
     * @return array<string,mixed>
     */
    private function upcomingEvent(?array $event): array
    {
        if ($event === null) {
            return $this->brief(
                group: 'upcoming_event',
                state: 'not_available',
                count: 0,
                owner: 'operations.events',
                canonicalUrl: '/events',
                facts: [],
            );
        }

        return $this->brief(
            group: 'upcoming_event',
            state: (string) ($event['state'] ?? 'unknown'),
            count: (int) ($event['count'] ?? 0),
            owner: 'operations.events',
            canonicalUrl: (string) ($event['handoff']['href'] ?? '/events'),
            facts: [[
                'code' => 'next_event',
                'owner' => 'operations.events',
                'state' => (string) ($event['state'] ?? 'unknown'),
                'count' => (int) ($event['count'] ?? 0),
                'metadata' => is_array($event['metadata'] ?? null) ? $event['metadata'] : [],
            ]],
        );
    }

    /** @return array<string,mixed> */
    private function postEvent(PlayerReference $actor, string $allianceId): array
    {
        if (! $this->eventAuthorization->allows(
            $actor->playerId,
            EventScope::Alliance,
            $allianceId,
            OperationsPermission::EventAllianceView,
        )) {
            return $this->brief(
                group: 'post_event_closeout',
                state: 'not_available',
                count: 0,
                owner: 'operations.events',
                canonicalUrl: '/events',
                facts: [],
            );
        }

        $candidates = EventOccurrence::query()
            ->where(static fn (Builder $query) => $query
                ->where('status', EventOccurrenceStatus::Completed->value)
                ->orWhere('ends_at', '<=', now()))
            ->where('ends_at', '>=', now()->subDays(self::CLOSEOUT_LOOKBACK_DAYS))
            ->whereHas('event', static fn (Builder $query) => $query
                ->where('scope', EventScope::Alliance->value)
                ->where('alliance_id', $allianceId)
                ->where('status', '!=', EventStatus::Cancelled->value))
            ->with(['event.eventType.workflowDimensions', 'event.occurrences'])
            ->orderByDesc('ends_at')
            ->orderByDesc('id')
            ->limit(self::CLOSEOUT_CANDIDATE_LIMIT)
            ->get();

        foreach ($candidates as $occurrence) {
            if (! $occurrence instanceof EventOccurrence || ! $occurrence->event instanceof Event) {
                continue;
            }

            $profile = $this->profiles->resolve($occurrence->event->eventType);
            if ($profile['profile_enabled'] !== true
                || ! in_array(
                    EventWorkflowDimension::ReadinessCloseout->value,
                    $profile['workflow_dimensions'],
                    true,
                )) {
                continue;
            }

            $command = $this->eventCommand->forEvent($actor, $occurrence->event, (string) $occurrence->id);
            $count = (int) ($command['blockerCount'] ?? 0);
            $href = '/events/'.(string) $occurrence->event->id.'/manage?occurrence='.(string) $occurrence->id;

            return $this->brief(
                group: 'post_event_closeout',
                state: (string) ($command['state'] ?? 'unknown'),
                count: $count,
                owner: 'operations.events',
                canonicalUrl: $href,
                facts: [[
                    'code' => 'event_closeout',
                    'owner' => 'operations.events',
                    'state' => (string) ($command['state'] ?? 'unknown'),
                    'count' => $count,
                    'eventId' => (string) $occurrence->event->id,
                    'occurrenceId' => (string) $occurrence->id,
                    'warningCount' => (int) ($command['warningCount'] ?? 0),
                    'endedAt' => $occurrence->ends_at->toIso8601String(),
                ]],
            );
        }

        return $this->brief(
            group: 'post_event_closeout',
            state: 'not_available',
            count: 0,
            owner: 'operations.events',
            canonicalUrl: '/events',
            facts: [],
        );
    }

    /**
     * @param  list<array<string,mixed>>  $facts
     * @return array<string,mixed>
     */
    private function brief(
        string $group,
        string $state,
        int $count,
        string $owner,
        string $canonicalUrl,
        array $facts,
    ): array {
        $fingerprintFacts = [
            'contract' => 'officer-brief-v1',
            'group' => $group,
            'state' => $state,
            'count' => max(0, $count),
            'owner' => $owner,
            'canonicalUrl' => $canonicalUrl,
            'facts' => $facts,
        ];

        return $fingerprintFacts + [
            'fingerprint' => hash('sha256', (string) json_encode($fingerprintFacts, JSON_THROW_ON_ERROR)),
        ];
    }
}
