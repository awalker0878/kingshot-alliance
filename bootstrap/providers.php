<?php

declare(strict_types=1);

use App\Contexts\Accounts\Authentication\Providers\AuthenticationServiceProvider;
use App\Contexts\Accounts\MultiFactorAuthentication\Providers\MultiFactorAuthenticationServiceProvider;
use App\Contexts\Accounts\Registration\Providers\RegistrationServiceProvider;
use App\Contexts\Alliance\Access\Providers\AccessServiceProvider;
use App\Contexts\Alliance\Content\Providers\ContentServiceProvider;
use App\Contexts\Alliance\Lifecycle\Providers\LifecycleServiceProvider;
use App\Contexts\Alliance\Membership\Providers\MembershipServiceProvider;
use App\Contexts\Alliance\Recruitment\Providers\RecruitmentServiceProvider;
use App\Contexts\Communications\Delivery\Providers\DeliveryServiceProvider;
use App\Contexts\GameWorld\GiftCodes\Providers\GiftCodesServiceProvider;
use App\Contexts\GameWorld\Governance\Providers\GovernanceServiceProvider;
use App\Contexts\GameWorld\KingdomMaps\Providers\KingdomMapsServiceProvider;
use App\Contexts\GameWorld\Players\Providers\PlayersServiceProvider;
use App\Contexts\Intelligence\Contributions\Providers\ContributionsServiceProvider;
use App\Contexts\Intelligence\Evidence\Providers\EvidenceServiceProvider;
use App\Contexts\Intelligence\Ingestion\Providers\IngestionServiceProvider;
use App\Contexts\Intelligence\Sharing\Providers\SharingServiceProvider;
use App\Contexts\Operations\KingPerks\Providers\KingPerksServiceProvider;
use App\Contexts\Operations\Participation\Reminders\Providers\RemindersServiceProvider;
use App\Contexts\Operations\TerritoryPlanning\Providers\TerritoryPlanningServiceProvider;
use App\Contexts\Platform\Administration\Providers\AdministrationServiceProvider;
use App\Contexts\Platform\AllianceAdministration\Providers\AllianceAdministrationServiceProvider;
use App\Contexts\Platform\DataGovernance\Providers\DataGovernanceServiceProvider;
use App\Contexts\Platform\Integrations\Providers\IntegrationsServiceProvider;
use App\ReadModels\AllianceAssistant\Providers\AllianceAssistantServiceProvider;
use App\ReadModels\AllianceGovernance\Providers\AllianceGovernanceServiceProvider;
use App\ReadModels\CommandOverview\Providers\CommandOverviewServiceProvider;
use App\ReadModels\IntelligenceSignals\Providers\IntelligenceSignalsServiceProvider;
use App\ReadModels\TerritoryPlanning\Providers\TerritoryPlanningReadModelServiceProvider;
use App\Shared\Infrastructure\Providers\InfrastructureServiceProvider;
use App\Workflows\KingdomGovernance\Providers\KingdomGovernanceServiceProvider;

return [
    InfrastructureServiceProvider::class,
    KingPerksServiceProvider::class,
    GovernanceServiceProvider::class,
    AuthenticationServiceProvider::class,
    RegistrationServiceProvider::class,
    MultiFactorAuthenticationServiceProvider::class,
    PlayersServiceProvider::class,
    GiftCodesServiceProvider::class,
    KingdomMapsServiceProvider::class,
    LifecycleServiceProvider::class,
    MembershipServiceProvider::class,
    AccessServiceProvider::class,
    ContentServiceProvider::class,
    RecruitmentServiceProvider::class,
    DeliveryServiceProvider::class,
    TerritoryPlanningServiceProvider::class,
    RemindersServiceProvider::class,
    TerritoryPlanningReadModelServiceProvider::class,
    AllianceGovernanceServiceProvider::class,
    AllianceAssistantServiceProvider::class,
    CommandOverviewServiceProvider::class,
    IntelligenceSignalsServiceProvider::class,
    KingdomGovernanceServiceProvider::class,
    EvidenceServiceProvider::class,
    ContributionsServiceProvider::class,
    IngestionServiceProvider::class,
    SharingServiceProvider::class,
    AdministrationServiceProvider::class,
    AllianceAdministrationServiceProvider::class,
    DataGovernanceServiceProvider::class,
    IntegrationsServiceProvider::class,
];
