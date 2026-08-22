<?php

declare(strict_types=1);

use App\Contexts\Accounts\Authentication\Providers\AuthenticationServiceProvider;
use App\Contexts\Accounts\MultiFactorAuthentication\Providers\MultiFactorAuthenticationServiceProvider;
use App\Contexts\Accounts\Registration\Providers\RegistrationServiceProvider;
use App\Contexts\Alliance\Lifecycle\Providers\LifecycleServiceProvider;
use App\Contexts\Alliance\Recruitment\Providers\RecruitmentServiceProvider;
use App\Contexts\GameWorld\GiftCodes\Providers\GiftCodesServiceProvider;
use App\Contexts\GameWorld\Players\Providers\PlayersServiceProvider;
use App\Contexts\Operations\KingPerks\Providers\KingPerksServiceProvider;
use App\Contexts\Operations\Participation\Providers\ParticipationServiceProvider;
use App\Contexts\Operations\TerritoryPlanning\Providers\TerritoryPlanningServiceProvider;
use App\Contexts\Platform\Administration\Providers\AdministrationServiceProvider;
use App\Contexts\Platform\Integrations\Providers\IntegrationsServiceProvider;
use App\ReadModels\TerritoryPlanning\Providers\TerritoryPlanningReadModelServiceProvider;
use App\Shared\Infrastructure\Providers\InfrastructureServiceProvider;

return [
    InfrastructureServiceProvider::class,
    AuthenticationServiceProvider::class,
    RegistrationServiceProvider::class,
    MultiFactorAuthenticationServiceProvider::class,
    PlayersServiceProvider::class,
    GiftCodesServiceProvider::class,
    LifecycleServiceProvider::class,
    RecruitmentServiceProvider::class,
    ParticipationServiceProvider::class,
    KingPerksServiceProvider::class,
    TerritoryPlanningServiceProvider::class,
    TerritoryPlanningReadModelServiceProvider::class,
    AdministrationServiceProvider::class,
    IntegrationsServiceProvider::class,
];
