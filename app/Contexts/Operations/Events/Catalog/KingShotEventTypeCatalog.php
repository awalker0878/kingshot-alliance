<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Catalog;

use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Events\Enums\EventCategory;
use App\Contexts\Operations\Events\Enums\EventProfileState;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventTypeVerificationState;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;

/**
 * Server-owned Kingshot event identity catalogue.
 *
 * Presence in this catalogue is not evidence of game mechanics. Only an event
 * whose identity is verified and whose profile is enabled may expose workflow
 * dimensions. Candidate identities deliberately have an empty disabled profile.
 */
final class KingShotEventTypeCatalog
{
    /** @return list<array<string, mixed>> */
    public static function definitions(): array
    {
        return [
            self::verifiedBearHunt(),
            self::candidate('viking-vengeance', EventCategory::AllianceActivity, 'shield', 20, [EventScope::Alliance]),
            self::candidate('alliance-mobilization', EventCategory::Competition, 'flag', 30, [EventScope::Alliance]),
            self::candidate('alliance-championship', EventCategory::Competition, 'trophy', 40, [EventScope::Alliance]),
            self::candidate('alliance-brawl', EventCategory::Competition, 'swords', 50, [EventScope::Alliance]),
            self::candidate('swordland-showdown', EventCategory::AllianceBattle, 'swords', 60, [EventScope::Alliance]),
            self::candidate('tri-alliance-clash', EventCategory::AllianceBattle, 'triangle', 70, [EventScope::Alliance]),
            self::candidate('flamedragon-tyrant', EventCategory::AllianceBattle, 'flame', 80, [EventScope::Alliance]),
            self::candidate('swordland-summit-league', EventCategory::AllianceBattle, 'crown', 90, [EventScope::Alliance]),
            self::candidate('cesares-fury', EventCategory::AllianceActivity, 'skull', 100, [EventScope::Alliance]),
            self::candidate('outpost-battle', EventCategory::AllianceBattle, 'fort', 110, [EventScope::Alliance]),
            self::candidate('sanctuary-battle', EventCategory::AllianceBattle, 'temple', 120, [EventScope::Alliance]),
            self::candidate('castle-battle', EventCategory::KingdomBattle, 'castle', 130, [EventScope::Alliance, EventScope::Kingdom]),
            self::candidate('kingdom-of-power', EventCategory::KingdomBattle, 'crown', 140, [EventScope::Kingdom]),
            self::candidate('hall-of-governors', EventCategory::Progression, 'medal', 200, [EventScope::Player]),
            self::candidate('armament-competition', EventCategory::Progression, 'hammer', 210, [EventScope::Player]),
            self::candidate('hero-roulette', EventCategory::Progression, 'sparkles', 220, [EventScope::Player]),
            self::candidate('fishing-tournament', EventCategory::Progression, 'fish', 230, [EventScope::Player]),
            self::candidate('treasure-raiders', EventCategory::Progression, 'map', 240, [EventScope::Player]),
            self::candidate('merchant-empire', EventCategory::Progression, 'coins', 250, [EventScope::Player]),
            self::candidate('eternitys-reach', EventCategory::Progression, 'compass', 260, [EventScope::Player]),
            self::unsupportedCustom(),
        ];
    }

    /** @return array<string, mixed> */
    private static function verifiedBearHunt(): array
    {
        return self::type(
            slug: 'bear-hunt',
            category: EventCategory::AllianceActivity,
            icon: 'paw',
            sortOrder: 10,
            scopes: [EventScope::Alliance],
            verificationState: EventTypeVerificationState::Verified,
            profileState: EventProfileState::Enabled,
            sourceLabel: 'Repository-supported Bear Hunt product contracts',
            sourceReference: 'docs/product/screenshot-intake.md',
            sourceObservedAt: '2026-08-28T00:00:00+00:00',
            workflowDimensions: [
                EventWorkflowDimension::Participation,
                EventWorkflowDimension::Roster,
                EventWorkflowDimension::Rallies,
                EventWorkflowDimension::Results,
                EventWorkflowDimension::ScreenshotEvidence,
                EventWorkflowDimension::Debrief,
                EventWorkflowDimension::ReadinessCloseout,
            ],
        );
    }

    /** @param list<EventScope> $scopes */
    private static function candidate(
        string $slug,
        EventCategory $category,
        string $icon,
        int $sortOrder,
        array $scopes,
    ): array {
        return self::type(
            slug: $slug,
            category: $category,
            icon: $icon,
            sortOrder: $sortOrder,
            scopes: $scopes,
            verificationState: EventTypeVerificationState::Candidate,
            profileState: EventProfileState::Disabled,
        );
    }

    /** @return array<string, mixed> */
    private static function unsupportedCustom(): array
    {
        return self::type(
            slug: 'custom',
            category: EventCategory::Custom,
            icon: 'calendar',
            sortOrder: 1000,
            scopes: [EventScope::Player, EventScope::Alliance, EventScope::Kingdom],
            verificationState: EventTypeVerificationState::Unsupported,
            profileState: EventProfileState::Disabled,
        );
    }

    /**
     * @param list<EventScope> $scopes
     * @param list<EventWorkflowDimension> $workflowDimensions
     * @return array<string, mixed>
     */
    private static function type(
        string $slug,
        EventCategory $category,
        string $icon,
        int $sortOrder,
        array $scopes,
        EventTypeVerificationState $verificationState,
        EventProfileState $profileState,
        ?string $sourceLabel = null,
        ?string $sourceReference = null,
        ?string $sourceObservedAt = null,
        ?string $gameVersionBoundary = null,
        array $workflowDimensions = [],
    ): array {
        $key = str_replace('-', '_', $slug);

        return [
            'slug' => $slug,
            'name_key' => "events.types.{$key}.name",
            'description_key' => "events.types.{$key}.description",
            'category' => $category,
            'icon_key' => $icon,
            'sort_order' => $sortOrder,
            'verification_state' => $verificationState,
            'profile_state' => $profileState,
            'source_label' => $sourceLabel,
            'source_reference' => $sourceReference,
            'source_observed_at' => $sourceObservedAt,
            'game_version_boundary' => $gameVersionBoundary,
            'workflow_dimensions' => $workflowDimensions,
            'scopes' => array_map(self::scope(...), $scopes),
        ];
    }

    /** @return array<string, mixed> */
    private static function scope(EventScope $scope): array
    {
        [$view, $create, $manage] = match ($scope) {
            EventScope::Player => [OperationsPermission::EventPlayerView, OperationsPermission::EventPlayerCreate, OperationsPermission::EventPlayerManage],
            EventScope::Alliance => [OperationsPermission::EventAllianceView, OperationsPermission::EventAllianceCreate, OperationsPermission::EventAllianceManage],
            EventScope::Kingdom => [OperationsPermission::EventKingdomView, OperationsPermission::EventKingdomCreate, OperationsPermission::EventKingdomManage],
        };

        return [
            'scope' => $scope,
            'view_permission' => $view,
            'create_permission' => $create,
            'manage_permission' => $manage,
        ];
    }
}
