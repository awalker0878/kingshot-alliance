<?php

declare(strict_types=1);

namespace App\ReadModels\EventManagement\Queries;

use App\Contexts\Alliance\Content\Queries\EventStrategyCommandQuery;
use App\Contexts\Communications\Delivery\Queries\EventDeliveryHealthQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Reminders\Queries\EventReminderCommandQuery;
use App\Contexts\Operations\TerritoryPlanning\Queries\EventTerritoryCommandQuery;
use App\ReadModels\EventManagement\Enums\EventCommandItemStatus as Status;
use App\ReadModels\EventManagement\Enums\EventCommandSeverity as Severity;
use App\ReadModels\EventManagement\Support\EventCommandItems as Items;
use App\ReadModels\EventManagement\Support\EventCommandOwnerReader;

final readonly class EventCommandContextReadinessQuery
{
    public function __construct(
        private EventStrategyCommandQuery $strategy,
        private EventTerritoryCommandQuery $territory,
        private EventReminderCommandQuery $reminders,
        private EventDeliveryHealthQuery $deliveries,
        private EventCommandOwnerReader $owners,
    ) {}

    /**
     * @param  list<string>  $dimensions
     * @return list<array<string, mixed>>
     */
    public function forOccurrence(PlayerReference $actor, Event $event, EventOccurrence $occurrence, array $dimensions): array
    {
        $sections = [];
        if ($event->scopeEnum() === EventScope::Alliance && is_string($event->alliance_id)) {
            $sections[] = $this->strategy($event, $occurrence);
        }
        if ($event->scopeEnum() !== EventScope::Player && $this->has($dimensions, EventWorkflowDimension::TerritoryPlan)) {
            $sections[] = $this->territory($actor, $event, $occurrence);
        }
        $sections[] = $this->communications($event, $occurrence);

        return $sections;
    }

    /** @return array<string, mixed> */
    private function strategy(Event $event, EventOccurrence $occurrence): array
    {
        $summary = $this->owners->read(
            'alliance.content',
            $event,
            $occurrence,
            fn (): array => $this->strategy->forEventType((string) $event->alliance_id, (string) $event->eventType->slug),
        );
        if ($summary === null) {
            return Items::section('strategy', 'events.command.sections.strategy', 'readiness', [
                Items::make('strategy.unavailable', 'readiness', Status::Unknown, Severity::Warning, 'alliance.content', 'events.command.items.strategyUnavailable', classification: 'alliance_strategy', handoff: ['href' => '/alliance/content', 'labelKey' => 'events.command.actions.openStrategy']),
            ]);
        }

        $guide = $summary['guide'] ?? null;
        if (! is_array($guide)) {
            return Items::section('strategy', 'events.command.sections.strategy', 'readiness', [
                Items::make('strategy.missing', 'readiness', Status::Warning, Severity::Warning, 'alliance.content', 'events.command.items.strategyMissing', classification: 'alliance_strategy', handoff: ['href' => '/alliance/content', 'labelKey' => 'events.command.actions.openStrategy']),
            ]);
        }

        $freshness = is_array($guide['freshness'] ?? null) ? $guide['freshness'] : [];
        $freshnessStatus = (string) ($freshness['status'] ?? 'stale');
        $needsReview = in_array($freshnessStatus, ['due_soon', 'stale'], true);
        $slug = (string) ($guide['slug'] ?? '');

        return Items::section('strategy', 'events.command.sections.strategy', 'readiness', [
            Items::make(
                'strategy.guide',
                'readiness',
                $needsReview ? Status::Warning : Status::Complete,
                $needsReview ? Severity::Warning : Severity::Informational,
                'alliance.content',
                $needsReview ? 'events.command.items.strategyNeedsReview' : 'events.command.items.strategyCurrent',
                ['title' => (string) ($guide['title'] ?? ''), 'freshness' => $freshnessStatus, 'revision' => (int) ($guide['revisionNumber'] ?? 0)],
                1,
                'alliance_strategy',
                ['href' => '/alliance/content/'.$slug, 'labelKey' => 'events.command.actions.openStrategy'],
                ['contentId' => (string) ($guide['id'] ?? ''), 'revisionNumber' => (int) ($guide['revisionNumber'] ?? 0)],
            ),
        ]);
    }

    /** @return array<string, mixed> */
    private function territory(PlayerReference $actor, Event $event, EventOccurrence $occurrence): array
    {
        $summary = $this->owners->read('operations.territory_planning', $event, $occurrence, fn (): array => $this->territory->forOccurrence($actor->playerId, $occurrence));
        if ($summary === null) {
            return Items::section('territory', 'events.command.sections.territory', 'readiness', [
                Items::make('territory.unavailable', 'readiness', Status::Unknown, Severity::Warning, 'operations.territory_planning', 'events.command.items.territoryUnavailable', handoff: Items::handoff($event, $occurrence, 'territory', 'events.command.actions.openTerritory')),
            ]);
        }
        if ((int) $summary['attachmentCount'] === 0) {
            return Items::section('territory', 'events.command.sections.territory', 'readiness', [
                Items::make('territory.not_attached', 'readiness', Status::NotApplicable, Severity::Informational, 'operations.territory_planning', 'events.command.items.territoryNotAttached'),
            ]);
        }

        $violations = (int) $summary['violationCount'];
        $warnings = (int) $summary['warningCount'];
        $items = [
            Items::make('territory.revision', 'readiness', $violations > 0 ? Status::NeedsAttention : Status::Complete, $violations > 0 ? Severity::Blocking : Severity::Informational, 'operations.territory_planning', $violations > 0 ? 'events.command.items.territoryViolations' : 'events.command.items.territoryReady', ['count' => $violations], $violations, handoff: Items::handoff($event, $occurrence, 'territory', 'events.command.actions.openTerritory'), source: ['references' => $summary['references']]),
        ];
        if ($warnings > 0) {
            $items[] = Items::make('territory.warnings', 'readiness', Status::Warning, Severity::Warning, 'operations.territory_planning', 'events.command.items.territoryWarnings', ['count' => $warnings], $warnings, handoff: Items::handoff($event, $occurrence, 'territory', 'events.command.actions.openTerritory'));
        }
        if ((bool) $summary['currentDraftDiffers']) {
            $items[] = Items::make('territory.newer_draft', 'readiness', Status::Warning, Severity::Warning, 'operations.territory_planning', 'events.command.items.territoryDraftDiffers', handoff: Items::handoff($event, $occurrence, 'territory', 'events.command.actions.openTerritory'));
        }

        return Items::section('territory', 'events.command.sections.territory', 'readiness', $items);
    }

    /** @return array<string, mixed> */
    private function communications(Event $event, EventOccurrence $occurrence): array
    {
        $reminder = $this->owners->read('operations.reminders', $event, $occurrence, fn (): array => $this->reminders->forEvent((string) $event->id));
        $delivery = $this->owners->read('communications.delivery', $event, $occurrence, fn (): array => $this->deliveries->forEventOccurrence((string) $occurrence->id));
        $items = [];

        if ($reminder === null) {
            $items[] = Items::make('communications.reminder_unavailable', 'readiness', Status::Unknown, Severity::Blocking, 'operations.reminders', 'events.command.items.reminderUnavailable', handoff: Items::handoff($event, $occurrence, 'reminders', 'events.command.actions.manageReminder'));
        } elseif ((int) $reminder['enabledBeforeStartCount'] === 0) {
            $items[] = Items::make('communications.reminder_missing', 'readiness', Status::NeedsAttention, Severity::Blocking, 'operations.reminders', 'events.command.items.reminderMissing', handoff: Items::handoff($event, $occurrence, 'reminders', 'events.command.actions.manageReminder'));
        } else {
            $enabled = (int) $reminder['enabledBeforeStartCount'];
            $items[] = Items::make('communications.reminder_scheduled', 'readiness', Status::Complete, Severity::Informational, 'operations.reminders', 'events.command.items.reminderScheduled', ['count' => $enabled], $enabled, handoff: Items::handoff($event, $occurrence, 'reminders', 'events.command.actions.manageReminder'));
        }

        if ($delivery === null) {
            $items[] = Items::make('communications.delivery_unavailable', 'readiness', Status::Unknown, Severity::Warning, 'communications.delivery', 'events.command.items.deliveryUnavailable', handoff: Items::handoff($event, $occurrence, 'reminders', 'events.command.actions.reviewDelivery'));
        } else {
            $failed = (int) $delivery['failedCount'];
            $pending = (int) $delivery['pendingCount'] + (int) $delivery['queuedCount'];
            if ($failed > 0) {
                $items[] = Items::make('communications.failed_deliveries', 'readiness', Status::NeedsAttention, Severity::Blocking, 'communications.delivery', 'events.command.items.deliveryFailed', ['count' => $failed, 'retryableCount' => (int) $delivery['retryableFailedCount']], $failed, handoff: Items::handoff($event, $occurrence, 'reminders', 'events.command.actions.reviewDelivery'));
            } elseif ($pending > 0) {
                $items[] = Items::make('communications.pending_deliveries', 'readiness', Status::Warning, Severity::Warning, 'communications.delivery', 'events.command.items.deliveryPending', ['count' => $pending], $pending, handoff: Items::handoff($event, $occurrence, 'reminders', 'events.command.actions.reviewDelivery'));
            } elseif ((int) $delivery['sentCount'] > 0) {
                $sent = (int) $delivery['sentCount'];
                $items[] = Items::make('communications.sent_deliveries', 'readiness', Status::Complete, Severity::Informational, 'communications.delivery', 'events.command.items.deliverySent', ['count' => $sent], $sent);
            }
        }

        return Items::section('communications', 'events.command.sections.communications', 'readiness', $items);
    }

    /** @param list<string> $dimensions */
    private function has(array $dimensions, EventWorkflowDimension $dimension): bool
    {
        return in_array($dimension->value, $dimensions, true);
    }
}
