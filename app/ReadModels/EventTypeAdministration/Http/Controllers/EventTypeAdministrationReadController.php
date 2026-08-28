<?php

declare(strict_types=1);

namespace App\ReadModels\EventTypeAdministration\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventTypeVerificationState;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventType;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Events\Services\EventTypeProfileResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class EventTypeAdministrationReadController
{
    public function __construct(
        private AccountIdentityQuery $accounts,
        private EventTypeProfileResolver $profiles,
    ) {}

    public function __invoke(Request $request): Response
    {
        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);
        $account = $this->accounts->require((int) $identifier);

        $types = EventType::query()
            ->with(['scopes', 'workflowDimensions'])
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get();

        return Inertia::render('Platform/EventTypes/Index', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'eventTypes' => array_values($types->map(fn (EventType $type): array => [
                'id' => (string) $type->id,
                'slug' => $type->slug,
                'nameKey' => $type->name_key,
                'descriptionKey' => $type->description_key,
                'category' => $type->categoryEnum()->value,
                'iconKey' => $type->icon_key,
                'active' => (bool) $type->is_active,
                'system' => (bool) $type->is_system,
                'profile' => $this->profiles->resolve($type),
                'scopes' => array_values($type->scopes
                    ->sortBy('sort_order')
                    ->values()
                    ->map(static fn (EventTypeScope $scope): array => [
                        'id' => (string) $scope->id,
                        'scope' => $scope->scopeEnum()->value,
                        'active' => (bool) $scope->is_active,
                        'viewPermission' => (string) $scope->view_permission_key,
                        'createPermission' => (string) $scope->create_permission_key,
                        'managePermission' => (string) $scope->manage_permission_key,
                    ])->all()),
            ])->all()),
            'scopeOptions' => array_map(static fn (EventScope $scope): string => $scope->value, EventScope::cases()),
            'verificationStateOptions' => array_map(static fn (EventTypeVerificationState $state): string => $state->value, EventTypeVerificationState::cases()),
            'workflowDimensionOptions' => array_map(static fn (EventWorkflowDimension $dimension): string => $dimension->value, EventWorkflowDimension::cases()),
        ]);
    }
}
