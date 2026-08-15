<?php

declare(strict_types=1);

namespace App\Domain\Events\Catalog;

use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventCategory;
use App\Domain\Events\Enums\EventRecurrencePolicy;
use App\Domain\Events\Enums\EventScheduleSource;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Enums\RecurrenceFrequency;

final class KingShotEventTypeCatalog
{
    /** @return list<array<string, mixed>> */
    public static function definitions(): array
    {
        return [
            self::type('bear-hunt', EventCategory::AllianceActivity, 'paw', 10, [
                self::scope(
                    EventScope::Alliance,
                    30,
                    null,
                    [EventCapability::Responses, EventCapability::Registration, EventCapability::Attendance, EventCapability::RallyGuidance, EventCapability::Formations, EventCapability::Results],
                    scheduleSource: EventScheduleSource::AllianceControlled,
                    recurrencePolicy: EventRecurrencePolicy::FixedInterval,
                    defaultRecurrenceFrequency: RecurrenceFrequency::Daily,
                    defaultRecurrenceInterval: 2,
                    minimumRepeatIntervalMinutes: 2880,
                    defaultSettings: [
                        'cooldown_minutes' => 2880,
                        'minimum_participation_interval_minutes' => 2760,
                        'starter_ranks' => ['r4', 'r5'],
                    ],
                ),
            ]),
            self::type('viking-vengeance', EventCategory::AllianceActivity, 'shield', 20, [
                self::scope(
                    EventScope::Alliance,
                    30,
                    null,
                    [EventCapability::Responses, EventCapability::Attendance, EventCapability::Phases, EventCapability::Results],
                    scheduleSource: EventScheduleSource::AllianceControlled,
                    defaultSettings: [
                        'town_center_min' => 7,
                        'starter_ranks' => ['r4', 'r5'],
                        'defense_failures_before_stop' => 2,
                    ],
                ),
            ]),
            self::type('alliance-mobilization', EventCategory::Competition, 'flag', 30, [
                self::scope(
                    EventScope::Alliance,
                    null,
                    null,
                    [EventCapability::Phases, EventCapability::Scoring, EventCapability::Results],
                    defaultSettings: ['alliance_members_min' => 15],
                ),
            ]),
            self::type('alliance-championship', EventCategory::Competition, 'trophy', 40, [
                self::scope(
                    EventScope::Alliance,
                    null,
                    null,
                    [EventCapability::Responses, EventCapability::Registration, EventCapability::Rosters, EventCapability::Teams, EventCapability::Attendance, EventCapability::Scoring, EventCapability::Results],
                    defaultSettings: [
                        'town_center_min' => 10,
                        'alliance_registrants_min' => 10,
                    ],
                ),
            ]),
            self::type('alliance-brawl', EventCategory::Competition, 'swords', 50, [
                self::scope(
                    EventScope::Alliance,
                    null,
                    null,
                    [EventCapability::Phases, EventCapability::Scoring, EventCapability::Results],
                    defaultSettings: [
                        'matchmaking_anchor_utc' => 'sunday 23:00',
                        'must_be_member_at_matchmaking' => true,
                    ],
                ),
            ]),
            self::type('swordland-showdown', EventCategory::AllianceBattle, 'swords', 60, [
                self::scope(
                    EventScope::Alliance,
                    60,
                    30,
                    [EventCapability::Responses, EventCapability::Polls, EventCapability::Phases, EventCapability::Rosters, EventCapability::Substitutes, EventCapability::Objectives, EventCapability::Attendance, EventCapability::Scoring, EventCapability::Results],
                    defaultSettings: [
                        'alliance_power_rank_max' => 20,
                        'combatant_capacity' => 30,
                        'substitute_capacity' => 10,
                    ],
                    capabilityConfiguration: [
                        EventCapability::Phases->value => [
                            'default_phases' => [
                                ['key' => 'voting', 'phase_type' => 'voting', 'name_key' => 'events.phases.voting', 'start_offset_minutes' => -8640, 'duration_minutes' => 2880],
                                ['key' => 'registration', 'phase_type' => 'registration', 'name_key' => 'events.phases.registration', 'start_offset_minutes' => -5760, 'duration_minutes' => 2880],
                                ['key' => 'matchmaking', 'phase_type' => 'matchmaking', 'name_key' => 'events.phases.matchmaking', 'start_offset_minutes' => -2880, 'duration_minutes' => 2880],
                                ['key' => 'battle', 'phase_type' => 'battle', 'name_key' => 'events.phases.battle', 'start_offset_minutes' => 0, 'duration_minutes' => 60],
                            ],
                        ],
                        EventCapability::Polls->value => [
                            'default_polls' => [
                                [
                                    'key' => 'battle-time',
                                    'poll_type' => 'time_vote',
                                    'question_key' => 'events.polls.swordland_battle_time.question',
                                    'opens_offset_minutes' => -8640,
                                    'closes_offset_minutes' => -5760,
                                    'max_choices' => 1,
                                    'manager_supplied_options' => true,
                                    'deadline_reminder_minutes' => 60,
                                ],
                            ],
                        ],
                        EventCapability::Rosters->value => [
                            'default_rosters' => [
                                ['key' => 'combatants', 'roster_type' => 'combatants', 'name_key' => 'events.rosters.combatants', 'assignment_group' => 'battlefield', 'capacity' => 30, 'sort_order' => 0],
                                ['key' => 'substitutes', 'roster_type' => 'substitutes', 'name_key' => 'events.rosters.substitutes', 'assignment_group' => 'battlefield', 'capacity' => 10, 'sort_order' => 10],
                            ],
                        ],
                    ],
                ),
            ]),
            self::type('tri-alliance-clash', EventCategory::AllianceBattle, 'triangle', 70, [
                self::scope(
                    EventScope::Alliance,
                    60,
                    null,
                    [EventCapability::Responses, EventCapability::Phases, EventCapability::Rosters, EventCapability::Legions, EventCapability::Substitutes, EventCapability::Objectives, EventCapability::Attendance, EventCapability::Scoring, EventCapability::Results],
                    defaultSettings: [
                        'opens_every' => 'monthly',
                        'voting_days' => ['monday', 'tuesday'],
                        'signup_days' => ['wednesday', 'thursday'],
                        'matchmaking_days' => ['friday'],
                        'battle_days' => ['saturday'],
                        'alliance_power_rank_max' => 20,
                        'max_legions' => 2,
                        'minimum_registrants_per_legion' => 15,
                        'matchmaking_top_player_count' => 20,
                    ],
                    capabilityConfiguration: [
                        EventCapability::Rosters->value => [
                            'default_rosters' => [
                                ['key' => 'legion-1', 'roster_type' => 'legion', 'name_key' => 'events.rosters.legion_1', 'assignment_group' => 'legion', 'sort_order' => 0],
                                ['key' => 'legion-2', 'roster_type' => 'legion', 'name_key' => 'events.rosters.legion_2', 'assignment_group' => 'legion', 'sort_order' => 10],
                            ],
                        ],
                    ],
                ),
            ]),
            self::type('flamedragon-tyrant', EventCategory::AllianceBattle, 'flame', 80, [
                self::scope(
                    EventScope::Alliance,
                    360,
                    60,
                    [EventCapability::Responses, EventCapability::Phases, EventCapability::Rosters, EventCapability::Substitutes, EventCapability::Objectives, EventCapability::Attendance, EventCapability::Scoring, EventCapability::Results],
                    defaultSettings: [
                        'preview_lead_days' => 7,
                        'signup_days' => ['monday', 'tuesday'],
                        'combatant_dispatch_days' => ['wednesday', 'thursday'],
                        'eve_of_battle_days' => ['friday', 'saturday', 'sunday'],
                        'battlefield_entry_utc' => 'sunday 11:00',
                        'battle_window_utc' => 'sunday 12:00-18:00',
                        'post_battle_window_utc' => 'sunday 18:00-19:00',
                        'combatant_capacity' => 60,
                    ],
                    capabilityConfiguration: [
                        EventCapability::Phases->value => [
                            'default_phases' => [
                                ['key' => 'battle-preparation', 'phase_type' => 'preparation', 'name_key' => 'events.phases.preparation', 'start_offset_minutes' => -5, 'duration_minutes' => 5],
                                ['key' => 'battle', 'phase_type' => 'battle', 'name_key' => 'events.phases.battle', 'start_offset_minutes' => 0, 'duration_minutes' => 360],
                            ],
                        ],
                        EventCapability::Rosters->value => [
                            'default_rosters' => [
                                ['key' => 'combatants', 'roster_type' => 'combatants', 'name_key' => 'events.rosters.combatants', 'assignment_group' => 'battlefield', 'capacity' => 60, 'sort_order' => 0],
                            ],
                        ],
                    ],
                ),
            ]),
            self::type('swordland-summit-league', EventCategory::AllianceBattle, 'crown', 90, [
                self::scope(
                    EventScope::Alliance,
                    60,
                    null,
                    [EventCapability::Responses, EventCapability::Polls, EventCapability::Phases, EventCapability::Rosters, EventCapability::Legions, EventCapability::Substitutes, EventCapability::Objectives, EventCapability::Attendance, EventCapability::Scoring, EventCapability::Results],
                    defaultSettings: [
                        'max_legions' => 2,
                        'combatant_capacity_per_legion' => 30,
                        'substitute_capacity_per_legion' => 20,
                        'substitute_entry_delay_minutes' => 3,
                    ],
                    capabilityConfiguration: [
                        EventCapability::Rosters->value => [
                            'default_rosters' => [
                                ['key' => 'legion-1', 'roster_type' => 'legion', 'name_key' => 'events.rosters.legion_1', 'assignment_group' => 'league', 'sort_order' => 0],
                                ['key' => 'legion-1-combatants', 'parent_key' => 'legion-1', 'roster_type' => 'combatants', 'name_key' => 'events.rosters.combatants', 'assignment_group' => 'league', 'capacity' => 30, 'sort_order' => 0],
                                ['key' => 'legion-1-substitutes', 'parent_key' => 'legion-1', 'roster_type' => 'substitutes', 'name_key' => 'events.rosters.substitutes', 'assignment_group' => 'league', 'capacity' => 20, 'sort_order' => 10],
                                ['key' => 'legion-2', 'roster_type' => 'legion', 'name_key' => 'events.rosters.legion_2', 'assignment_group' => 'league', 'sort_order' => 20],
                                ['key' => 'legion-2-combatants', 'parent_key' => 'legion-2', 'roster_type' => 'combatants', 'name_key' => 'events.rosters.combatants', 'assignment_group' => 'league', 'capacity' => 30, 'sort_order' => 0],
                                ['key' => 'legion-2-substitutes', 'parent_key' => 'legion-2', 'roster_type' => 'substitutes', 'name_key' => 'events.rosters.substitutes', 'assignment_group' => 'league', 'capacity' => 20, 'sort_order' => 10],
                            ],
                        ],
                    ],
                ),
            ]),
            self::type('cesares-fury', EventCategory::AllianceActivity, 'skull', 100, [
                self::scope(
                    EventScope::Alliance,
                    null,
                    null,
                    [EventCapability::Responses, EventCapability::Phases, EventCapability::RallyGuidance, EventCapability::Formations, EventCapability::Scoring, EventCapability::Results],
                    defaultSettings: [
                        'elite_summon_ranks' => ['r4', 'r5'],
                        'difficulty_locked_after_selection' => true,
                    ],
                ),
            ]),
            self::type('outpost-battle', EventCategory::AllianceBattle, 'fort', 110, [
                self::scope(
                    EventScope::Alliance,
                    120,
                    null,
                    [EventCapability::Responses, EventCapability::Rosters, EventCapability::Objectives, EventCapability::Attendance, EventCapability::Scoring, EventCapability::Results],
                    scheduleSource: EventScheduleSource::Matchmaking,
                    defaultSettings: [
                        'occupation_minutes_by_level' => [1 => 15, 2 => 20, 3 => 25, 4 => 30],
                        'territory_adjacency_required' => true,
                    ],
                    capabilityConfiguration: [EventCapability::Objectives->value => ['occupation_required_minutes' => 30]],
                ),
            ]),
            self::type('sanctuary-battle', EventCategory::AllianceBattle, 'temple', 120, [
                self::scope(
                    EventScope::Alliance,
                    120,
                    null,
                    [EventCapability::Responses, EventCapability::Registration, EventCapability::Phases, EventCapability::Rosters, EventCapability::Objectives, EventCapability::Attendance, EventCapability::Scoring, EventCapability::Results],
                    scheduleSource: EventScheduleSource::Matchmaking,
                    defaultSettings: [
                        'signup_ranks' => ['r4', 'r5'],
                        'alliance_members_min' => 20,
                        'occupation_required_minutes' => 30,
                        'defense_phase_lead_minutes' => 2880,
                    ],
                    capabilityConfiguration: [EventCapability::Objectives->value => ['occupation_required_minutes' => 30]],
                ),
            ]),
            self::type('castle-battle', EventCategory::KingdomBattle, 'castle', 130, [
                self::scope(EventScope::Alliance, 300, null, [EventCapability::Responses, EventCapability::Rosters, EventCapability::Objectives, EventCapability::Attendance, EventCapability::Scoring, EventCapability::Results], defaultSettings: ['consecutive_occupation_win_minutes' => 150, 'king_selection_minutes' => 600]),
                self::scope(EventScope::Kingdom, 300, null, [EventCapability::Phases, EventCapability::Rosters, EventCapability::Teams, EventCapability::Objectives, EventCapability::Attendance, EventCapability::Scoring, EventCapability::Results], defaultSettings: ['consecutive_occupation_win_minutes' => 150, 'king_selection_minutes' => 600]),
            ]),
            self::type('kingdom-of-power', EventCategory::KingdomBattle, 'crown', 140, [
                self::scope(
                    EventScope::Kingdom,
                    300,
                    null,
                    [EventCapability::Phases, EventCapability::Rosters, EventCapability::Teams, EventCapability::Objectives, EventCapability::Attendance, EventCapability::Scoring, EventCapability::Results],
                    defaultSettings: [
                        'preparation_phase_minutes' => 7800,
                        'battle_phase_start_utc' => 'saturday 10:00',
                        'battle_phase_total_minutes' => 720,
                        'castle_battle_minutes' => 300,
                        'consecutive_occupation_win_minutes' => 150,
                    ],
                ),
            ]),
            self::type('hall-of-governors', EventCategory::Progression, 'medal', 200, [self::scope(EventScope::Player, null, null, [EventCapability::Phases, EventCapability::Scoring, EventCapability::Results])]),
            self::type('armament-competition', EventCategory::Progression, 'hammer', 210, [self::scope(EventScope::Player, null, null, [EventCapability::Phases, EventCapability::Scoring, EventCapability::Results])]),
            self::type('hero-roulette', EventCategory::Progression, 'sparkles', 220, [self::scope(EventScope::Player, null, null, [EventCapability::Results])]),
            self::type('fishing-tournament', EventCategory::Progression, 'fish', 230, [self::scope(EventScope::Player, null, null, [EventCapability::Scoring, EventCapability::Results])]),
            self::type('treasure-raiders', EventCategory::Progression, 'map', 240, [self::scope(EventScope::Player, null, null, [EventCapability::Phases, EventCapability::Scoring, EventCapability::Results])]),
            self::type('merchant-empire', EventCategory::Progression, 'coins', 250, [self::scope(EventScope::Player, null, null, [EventCapability::Phases, EventCapability::Scoring, EventCapability::Results])]),
            self::type('eternitys-reach', EventCategory::Progression, 'compass', 260, [
                self::scope(
                    EventScope::Player,
                    null,
                    null,
                    [EventCapability::Registration, EventCapability::Phases, EventCapability::Attendance, EventCapability::Scoring, EventCapability::Results],
                    defaultRegistrationOpensMinutesBefore: 10,
                    defaultSettings: [
                        'observed_cycle_minutes' => 20160,
                        'town_center_min' => 16,
                        'registration_time_slots' => 6,
                        'registrations_per_player' => 1,
                    ],
                ),
            ]),
            self::type('custom', EventCategory::Custom, 'calendar', 1000, [
                self::scope(EventScope::Player, 60, null, [EventCapability::Responses, EventCapability::Registration, EventCapability::Attendance, EventCapability::Phases, EventCapability::Polls, EventCapability::Rosters, EventCapability::Objectives, EventCapability::Scoring, EventCapability::Results], scheduleSource: EventScheduleSource::Manual, recurrencePolicy: EventRecurrencePolicy::Configurable),
                self::scope(EventScope::Alliance, 60, null, [EventCapability::Responses, EventCapability::Registration, EventCapability::Waitlist, EventCapability::Attendance, EventCapability::Phases, EventCapability::Polls, EventCapability::Rosters, EventCapability::Substitutes, EventCapability::Teams, EventCapability::Legions, EventCapability::RallyGuidance, EventCapability::Formations, EventCapability::Objectives, EventCapability::Scoring, EventCapability::Results], scheduleSource: EventScheduleSource::Manual, recurrencePolicy: EventRecurrencePolicy::Configurable),
                self::scope(EventScope::Kingdom, 60, null, [EventCapability::Responses, EventCapability::Registration, EventCapability::Waitlist, EventCapability::Attendance, EventCapability::Phases, EventCapability::Polls, EventCapability::Rosters, EventCapability::Substitutes, EventCapability::Teams, EventCapability::Legions, EventCapability::RallyGuidance, EventCapability::Formations, EventCapability::Objectives, EventCapability::Scoring, EventCapability::Results], scheduleSource: EventScheduleSource::Manual, recurrencePolicy: EventRecurrencePolicy::Configurable),
            ]),
        ];
    }

    /** @param list<array<string, mixed>> $scopes */
    private static function type(string $slug, EventCategory $category, string $icon, int $sortOrder, array $scopes): array
    {
        $key = str_replace('-', '_', $slug);

        $instructionsKey = "events.types.{$key}.instructions";
        $scopes = array_map(static function (array $scope) use ($instructionsKey): array {
            $scope['default_instructions_key'] ??= $instructionsKey;

            return $scope;
        }, $scopes);

        return [
            'slug' => $slug,
            'name_key' => "events.types.{$key}.name",
            'description_key' => "events.types.{$key}.description",
            'category' => $category,
            'icon_key' => $icon,
            'sort_order' => $sortOrder,
            'scopes' => $scopes,
        ];
    }

    /**
     * @param  list<EventCapability>  $capabilities
     * @param  array<string, array<string, int|string|bool|array<array-key, mixed>>>  $capabilityConfiguration
     * @return array<string, mixed>
     */
    private static function scope(
        EventScope $scope,
        ?int $duration,
        ?int $capacity,
        array $capabilities,
        EventScheduleSource $scheduleSource = EventScheduleSource::GameCalendar,
        EventRecurrencePolicy $recurrencePolicy = EventRecurrencePolicy::Disabled,
        RecurrenceFrequency $defaultRecurrenceFrequency = RecurrenceFrequency::None,
        int $defaultRecurrenceInterval = 1,
        ?int $minimumRepeatIntervalMinutes = null,
        ?int $defaultRegistrationOpensMinutesBefore = null,
        ?int $defaultRegistrationClosesMinutesBefore = null,
        ?string $defaultInstructionsKey = null,
        array $defaultSettings = [],
        array $capabilityConfiguration = [],
    ): array {
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
            'default_duration_minutes' => $duration,
            'default_capacity' => $capacity,
            'schedule_source' => $scheduleSource,
            'recurrence_policy' => $recurrencePolicy,
            'default_recurrence_frequency' => $defaultRecurrenceFrequency,
            'default_recurrence_interval' => $defaultRecurrenceInterval,
            'minimum_repeat_interval_minutes' => $minimumRepeatIntervalMinutes,
            'default_registration_opens_minutes_before' => $defaultRegistrationOpensMinutesBefore,
            'default_registration_closes_minutes_before' => $defaultRegistrationClosesMinutesBefore,
            'default_instructions_key' => $defaultInstructionsKey,
            'default_settings' => $defaultSettings,
            'capability_configuration' => $capabilityConfiguration,
            'capabilities' => $capabilities,
        ];
    }
}
